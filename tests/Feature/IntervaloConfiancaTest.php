<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\BancaMembro;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Models\User;
use App\Services\IndiceQualidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class IntervaloConfiancaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private Programa $programa;
    private Banca $banca;
    private User $orientador;
    private BaremaItem $rigor;
    private Barema $barema;
    private IndiceQualidadeService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->programa, $this->banca, , $this->orientador] = $this->criarCenarioCompleto('defesa', false);
        $this->banca->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada', 'data_hora' => '2026-05-10 14:00:00']);
        $this->banca->trabalhoAcademico->publicacoes()->delete();

        $profile = DocenteProfile::create(['user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => 'USP', 'ativo' => true]);
        $vinculo = DocenteProgramaVinculo::create(['programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id, 'tipo_vinculo' => 'externo', 'pode_orientar' => false, 'pode_avaliar' => true, 'ativo' => true]);
        BancaMembro::create(['banca_id' => $this->banca->id, 'docente_programa_vinculo_id' => $vinculo->id, 'papel' => 'avaliador', 'ordem' => 3, 'ativo' => true]);

        $this->barema = Barema::create(['programa_id' => $this->programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $secao = $this->barema->secoes()->create(['nome' => 'Seção', 'peso' => 1, 'ordem' => 1, 'ativo' => true]);
        $this->rigor = BaremaItem::create(['barema_secao_id' => $secao->id, 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo('rigor')->id,
            'nome' => 'Rigor', 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true]);
        PeriodoQualidade::create(['programa_id' => $this->programa->id, 'nome' => 'Quadriênio', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);
        $this->servico = app(IndiceQualidadeService::class);
    }

    private function notas(array $porAvaliador): void
    {
        $membros = $this->banca->avaliadores()->orderBy('ordem')->get()->values();
        foreach ($porAvaliador as $i => $nota) {
            $avaliacao = BancaAvaliacao::create(['banca_id' => $this->banca->id, 'banca_membro_id' => $membros[$i]->id, 'barema_id' => $this->barema->id, 'status' => 'enviada', 'enviada_em' => now()]);
            $avaliacao->notas()->create(['barema_item_id' => $this->rigor->id, 'dimensao_qualidade_id' => $this->rigor->dimensao_qualidade_id, 'nota' => $nota]);
        }
    }

    public function test_intervalo_do_trabalho_contem_o_indice(): void
    {
        $this->notas([6, 8, 10]);

        $r = $this->servico->indiceTrabalho($this->banca, comIntervalo: true);

        [$lo, $hi] = $r['intervalo'];
        $this->assertLessThanOrEqual($r['indice'], $lo);
        $this->assertGreaterThanOrEqual($r['indice'], $hi);
        $this->assertLessThan($hi, $lo);
    }

    public function test_sem_intervalo_quando_nao_pedido(): void
    {
        $this->notas([6, 8, 10]);

        $this->assertArrayNotHasKey('intervalo', $this->servico->indiceTrabalho($this->banca));
    }

    public function test_um_avaliador_intervalo_degenerado(): void
    {
        $this->notas([8]);

        $r = $this->servico->indiceTrabalho($this->banca, comIntervalo: true);

        $this->assertEquals([8.0, 8.0], $r['intervalo']);
        $this->assertNull($r['fronteira']);
    }

    public function test_intervalo_e_reprodutivel(): void
    {
        $this->notas([5, 8, 10]);

        $this->assertSame(
            $this->servico->indiceTrabalho($this->banca, comIntervalo: true)['intervalo'],
            $this->servico->indiceTrabalho($this->banca->fresh(), comIntervalo: true)['intervalo'],
        );
    }

    public function test_fronteira_quando_cruza_faixa(): void
    {
        $this->notas([7.5, 8, 10]);

        $r = $this->servico->indiceTrabalho($this->banca, comIntervalo: true);

        $this->assertEqualsWithDelta(8.0, $r['indice'], 0.001);
        $this->assertSame(2, $r['classe']);
        $this->assertSame(1, $r['fronteira']);
    }

    public function test_sem_fronteira_quando_regra_de_corte_muda_a_classe(): void
    {
        $this->notas([7.5, 8, 10]);
        $this->banca->update(['reprovada_por_plagio' => true]);

        $r = $this->servico->indiceTrabalho($this->banca->fresh(), comIntervalo: true);

        $this->assertSame(5, $r['classe']);
        $this->assertNull($r['fronteira']);
    }

    public function test_intervalo_nao_altera_o_gerador_aleatorio_global(): void
    {
        $this->notas([6, 8, 10]);
        mt_srand(42);
        $esperado = [mt_rand(), mt_rand()];

        mt_srand(42);
        $primeiro = mt_rand();
        $this->servico->indiceTrabalho($this->banca, comIntervalo: true);

        $this->assertSame($esperado[1], mt_rand());
        $this->assertSame($esperado[0], $primeiro);
    }

    public function test_programa_usa_o_periodo_escolhido_para_cada_trabalho(): void
    {
        $this->notas([8, 8, 8]);
        PeriodoQualidade::create(['programa_id' => $this->programa->id, 'nome' => '2026', 'inicio_em' => '2026-01-01', 'fim_em' => '2026-12-31', 'corte_alta' => 9.5, 'corte_boa' => 9, 'corte_suficiente' => 8.5, 'corte_pouca' => 8]);
        $quadrienio = PeriodoQualidade::where('nome', 'Quadriênio')->first();

        $r = $this->servico->indicePrograma($quadrienio);

        $this->assertSame(2, reset($r['trabalhos'])['classe']);
        $this->assertSame(1, $r['distribuicao'][2]);
    }

    public function test_orientador_nao_ve_perfil_com_um_so_avaliador(): void
    {
        $this->notas([8]);

        $this->actingAs($this->orientador)->get(route('avaliacoes.banca', $this->banca))
            ->assertSee('Como a banca interpretou o trabalho')
            ->assertSee('perfil por dimensão só aparece com pelo menos dois avaliadores');
    }

    public function test_intervalo_do_programa(): void
    {
        $this->notas([6, 8, 10]);

        $r = $this->servico->indicePrograma(PeriodoQualidade::first());

        $this->assertCount(2, $r['intervalo']);
        $this->assertLessThanOrEqual($r['indice'], $r['intervalo'][0]);
        $this->assertGreaterThanOrEqual($r['indice'], $r['intervalo'][1]);
        $this->assertArrayHasKey('intervalo', reset($r['trabalhos']));
    }

    public function test_orientador_nao_ve_intervalo(): void
    {
        $this->notas([6, 8, 10]);

        $this->actingAs($this->orientador)->get(route('avaliacoes.banca', $this->banca))
            ->assertSee('Como a banca interpretou o trabalho')->assertDontSee('IC 95%');
    }

    public function test_cpa_ve_intervalo(): void
    {
        $this->notas([7.5, 8, 10]);
        $membro = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $membro->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato']);

        $this->actingAs($membro)->get(route('cpa.index'))->assertOk()->assertSee('IC 95%')->assertSee('fronteira com Alta qualidade');
    }
}
