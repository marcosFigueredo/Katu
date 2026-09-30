<?php

namespace Tests\Feature;

use App\Models\AreaConcentracao;
use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\Banca;
use App\Models\Barema;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\LinhaPesquisa;
use App\Models\MabacAnalise;
use App\Models\Programa;
use App\Models\SwaraSessao;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\AutoavaliacaoIndicadorService;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsolamentoAnaliticoTest extends TestCase
{
    use RefreshDatabase;

    private function programa(string $sufixo): array
    {
        $secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => "Programa $sufixo"]);
        app(ContextoSecretaria::class)->vincular($secretaria, $programa);

        $area = AreaConcentracao::create(['programa_id' => $programa->id, 'nome' => "Área $sufixo", 'ativo' => true]);
        $linha = LinhaPesquisa::create(['area_concentracao_id' => $area->id, 'nome' => "Linha $sufixo", 'ativo' => true]);
        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id, 'linha_pesquisa_id' => $linha->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'data_ingresso' => now()->subMonths(20)->toDateString(), 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id, 'linha_pesquisa_id' => $linha->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $banca = Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'finalizada',
            'resultado' => 'aprovado', 'nota_media' => 8.5, 'data_hora' => now()->subMonth(), 'libera_envio_trabalho' => true,
        ]);
        $barema = Barema::create([
            'programa_id' => $programa->id, 'nome' => "Barema $sufixo", 'tipo' => 'qualificacao',
            'nivel' => 'ambos', 'versao' => '1.0', 'ativo' => true,
        ]);
        $periodo = AutoavaliacaoPeriodo::create([
            'programa_id' => $programa->id, 'nome' => "Período $sufixo", 'ano' => now()->year,
            'data_inicio' => now()->subYear()->toDateString(), 'data_fim' => now()->toDateString(), 'status' => 'concluido',
        ]);
        $mabac = MabacAnalise::create([
            'barema_id' => $barema->id, 'nome' => "Análise $sufixo", 'tipo_banca' => 'ambos',
            'periodo_inicio' => now()->subYear()->toDateString(), 'periodo_fim' => now()->toDateString(), 'criado_por' => 'teste',
        ]);
        $swara = SwaraSessao::create(['barema_id' => $barema->id, 'nome' => "Sessão $sufixo"]);

        return compact('secretaria', 'programa', 'trabalho', 'banca', 'barema', 'periodo', 'mabac', 'swara');
    }

    public function test_relatorio_de_bancas_mostra_apenas_o_programa(): void
    {
        $a = $this->programa('A');
        $this->programa('B');

        $this->actingAs($a['secretaria'])->get(route('relatorios.bancas'))
            ->assertOk()->assertSee('Linha A')->assertDontSee('Linha B');
    }

    public function test_painel_de_relatorios_conta_apenas_o_programa(): void
    {
        $a = $this->programa('A');
        $this->programa('B');

        $this->actingAs($a['secretaria'])->get(route('relatorios.dashboard'))
            ->assertOk()
            ->assertViewHas('kpis', fn ($kpis) => $kpis['totalBancas'] === 1);
    }

    public function test_relatorios_de_mabac_swara_e_autoavaliacao_filtram_pelo_programa(): void
    {
        $a = $this->programa('A');
        $this->programa('B');
        $sec = $a['secretaria'];

        $this->actingAs($sec)->get(route('relatorios.mabac'))->assertOk()->assertSee('Análise A')->assertDontSee('Análise B');
        $this->actingAs($sec)->get(route('relatorios.swara'))->assertOk()->assertSee('Sessão A')->assertDontSee('Sessão B');
        $this->actingAs($sec)->get(route('relatorios.autoavaliacao'))->assertOk()->assertDontSee('Período B');
    }

    public function test_telas_analiticas_listam_apenas_o_programa(): void
    {
        $a = $this->programa('A');
        $this->programa('B');
        $sec = $a['secretaria'];

        $this->actingAs($sec)->get(route('autoavaliacao.index'))->assertOk()->assertSee('Período A')->assertDontSee('Período B');
        $this->actingAs($sec)->get(route('mabac.index'))->assertOk()->assertSee('Análise A')->assertDontSee('Análise B');
        $this->actingAs($sec)->get(route('mabac.create'))->assertOk()->assertSee('Barema A')->assertDontSee('Barema B');
    }

    public function test_recursos_analiticos_de_outro_programa_retornam_403(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');
        $sec = $a['secretaria'];

        $this->actingAs($sec)->get(route('autoavaliacao.show', $b['periodo']))->assertForbidden();
        $this->actingAs($sec)->post(route('autoavaliacao.calcular', $b['periodo']))->assertForbidden();
        $this->actingAs($sec)->delete(route('autoavaliacao.destroy', $b['periodo']))->assertForbidden();
        $this->actingAs($sec)->get(route('mabac.show', $b['mabac']))->assertForbidden();
        $this->actingAs($sec)->delete(route('mabac.destroy', $b['mabac']))->assertForbidden();
        $this->actingAs($sec)->get(route('swara.index', $b['barema']))->assertForbidden();
        $this->actingAs($sec)->get(route('swara.show', [$b['barema'], $b['swara']]))->assertForbidden();
        $this->actingAs($sec)->get(route('bibliometria.show', $b['trabalho']))->assertForbidden();
        $this->actingAs($sec)->post(route('mabac.store'), [
            'nome' => 'X', 'barema_id' => $b['barema']->id, 'tipo_banca' => 'ambos',
            'periodo_inicio' => now()->subYear()->toDateString(), 'periodo_fim' => now()->toDateString(),
        ])->assertSessionHasErrors('barema_id');
        $this->assertDatabaseHas('autoavaliacao_periodos', ['id' => $b['periodo']->id]);
    }

    public function test_indicadores_da_autoavaliacao_consideram_apenas_bancas_do_programa(): void
    {
        $a = $this->programa('A');
        $this->programa('B');

        app(AutoavaliacaoIndicadorService::class)->calcular($a['periodo']);

        $a01 = AutoavaliacaoIndicador::where('autoavaliacao_periodo_id', $a['periodo']->id)->where('codigo', 'A01')->firstOrFail();
        $this->assertSame(1.0, (float) $a01->valor_numerico);
    }
}
