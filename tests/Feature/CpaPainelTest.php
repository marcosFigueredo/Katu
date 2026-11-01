<?php

namespace Tests\Feature;

use App\Models\AreaConcentracao;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use App\Models\DiscenteProfile;
use App\Models\LinhaPesquisa;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Models\User;
use App\Services\CpaPainelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class CpaPainelTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private Programa $programa;
    private Banca $banca;
    private User $discente;
    private User $orientador;
    private Barema $barema;
    private BaremaItem $rigor;
    private PeriodoQualidade $periodo;
    private LinhaPesquisa $linhaA;
    private LinhaPesquisa $linhaB;
    private User $membro;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->programa, $this->banca, $this->discente, $this->orientador] = $this->criarCenarioCompleto('defesa', false);
        $this->banca->trabalhoAcademico->publicacoes()->delete();
        $area = AreaConcentracao::create(['programa_id' => $this->programa->id, 'nome' => 'Modelagem', 'ativo' => true]);
        $this->linhaA = LinhaPesquisa::create(['area_concentracao_id' => $area->id, 'nome' => 'Linha A', 'ativo' => true]);
        $this->linhaB = LinhaPesquisa::create(['area_concentracao_id' => $area->id, 'nome' => 'Linha B', 'ativo' => true]);
        $this->banca->trabalhoAcademico->update(['linha_pesquisa_id' => $this->linhaA->id, 'titulo' => 'Título Secreto da Dissertação']);
        $this->banca->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada', 'data_hora' => '2025-05-10 14:00:00']);

        $this->barema = Barema::create(['programa_id' => $this->programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $secao = $this->barema->secoes()->create(['nome' => 'Seção', 'peso' => 1, 'ordem' => 1, 'ativo' => true]);
        $this->rigor = BaremaItem::create(['barema_secao_id' => $secao->id, 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo('rigor')->id,
            'nome' => 'Rigor do método', 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true]);
        $this->periodo = PeriodoQualidade::create(['programa_id' => $this->programa->id, 'nome' => 'Quadriênio', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);

        $this->membro = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $this->membro->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato']);
    }

    private function notas(Banca $banca, float $nota): void
    {
        foreach ($banca->avaliadores()->get() as $membro) {
            $avaliacao = BancaAvaliacao::create(['banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $this->barema->id, 'status' => 'enviada', 'enviada_em' => now()]);
            $avaliacao->notas()->create(['barema_item_id' => $this->rigor->id, 'dimensao_qualidade_id' => $this->rigor->dimensao_qualidade_id, 'nota' => $nota]);
        }
    }

    private function outraDefesa(string $data, ?LinhaPesquisa $linha = null, string $nivel = 'mestrado'): Banca
    {
        $original = $this->banca->trabalhoAcademico;
        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = $original->discenteVinculo->replicate();
        $vinculo->discente_profile_id = $profile->id;
        $vinculo->save();
        $trabalho = $original->replicate();
        $trabalho->fill(['discente_programa_vinculo_id' => $vinculo->id, 'linha_pesquisa_id' => ($linha ?? $this->linhaA)->id, 'nivel' => $nivel]);
        $trabalho->save();
        $outra = Banca::create(['trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa', 'status' => 'finalizada', 'resultado' => 'aprovado',
            'homologacao_status' => 'homologada', 'data_hora' => $data]);
        foreach ($this->banca->avaliadores()->get() as $membro) {
            $outra->membros()->create(['docente_programa_vinculo_id' => $membro->docente_programa_vinculo_id, 'papel' => 'avaliador', 'ordem' => $membro->ordem, 'ativo' => true]);
        }

        return $outra;
    }

    private function painel(array $filtros = []): array
    {
        return app(CpaPainelService::class)->montar($this->periodo, $filtros, $this->membro);
    }

    public function test_quartis_tipo_7(): void
    {
        $this->assertSame(['min' => 1.0, 'q1' => 1.75, 'mediana' => 2.5, 'q3' => 3.25, 'max' => 4.0, 'n' => 4], CpaPainelService::quartis([4, 1, 3, 2]));
        $this->assertNull(CpaPainelService::quartis([]));
    }

    public function test_panorama_sem_nomes(): void
    {
        $this->notas($this->banca, 8);

        $painel = $this->painel();
        $this->assertSame(1, $painel['panorama']['n']);
        $this->assertEqualsWithDelta(8.0, $painel['panorama']['resumo']['rigor']['mediana'], 0.001);

        $this->actingAs($this->membro)->get(route('cpa.index'))
            ->assertOk()
            ->assertSee('Panorama do quadriênio')
            ->assertDontSee($this->discente->name)
            ->assertDontSee($this->orientador->name)
            ->assertDontSee('Título Secreto da Dissertação');
    }

    public function test_evolucao_por_ano(): void
    {
        $this->notas($this->banca, 8);
        $this->notas($this->outraDefesa('2026-03-01'), 6);
        $this->notas($this->outraDefesa('2026-09-01'), 9);

        $evolucao = $this->painel()['evolucao'];

        $this->assertSame([2025, 2026], array_keys($evolucao));
        $this->assertSame(2, $evolucao[2026]['n']);
        $this->assertEqualsWithDelta(7.5, $evolucao[2026]['dimensoes']['rigor'], 0.001);
    }

    public function test_recortes_por_linha_nivel_e_bolsa(): void
    {
        $this->notas($this->banca, 8);
        $this->notas($this->outraDefesa('2026-03-01', $this->linhaB, 'doutorado'), 6);
        $this->banca->trabalhoAcademico->discenteVinculo->bolsas()->create(['agencia' => 'capes', 'modalidade' => 'mestrado', 'inicio_em' => '2024-03-01']);

        $recortes = $this->painel()['recortes'];

        $this->assertSame(1, $recortes['linha']['Linha A']['n']);
        $this->assertEqualsWithDelta(6.0, $recortes['linha']['Linha B']['mediana'], 0.001);
        $this->assertSame(1, $recortes['nivel']['Doutorado']['n']);
        $this->assertSame(1, $recortes['bolsa']['Bolsista']['n']);
        $this->assertSame(1, $recortes['bolsa']['Não bolsista']['n']);
        $this->assertSame(2, $recortes['area']['Modelagem']['n']);
    }

    public function test_filtro_por_linha(): void
    {
        $this->notas($this->banca, 8);
        $this->notas($this->outraDefesa('2026-03-01', $this->linhaB), 6);

        $painel = $this->painel(['linhas' => [$this->linhaB->id]]);

        $this->assertSame(1, $painel['panorama']['n']);
        $this->assertEqualsWithDelta(6.0, $painel['panorama']['indice'], 0.001);
    }

    public function test_plagio_fora_do_painel(): void
    {
        $this->notas($this->banca, 8);
        $this->banca->update(['reprovada_por_plagio' => true]);

        $painel = $this->painel();
        $this->assertSame(0, $painel['panorama']['n']);
        $this->assertSame([], $painel['evolucao']);
    }

    public function test_recorte_vazio_sem_erro(): void
    {
        $painel = $this->painel(['linhas' => [$this->linhaB->id]]);

        $this->assertSame(0, $painel['panorama']['n']);
        $this->assertNull($painel['panorama']['indice']);
        $this->actingAs($this->membro)->get(route('cpa.index', ['linhas' => [$this->linhaB->id]]))->assertOk()->assertSee('Sem trabalhos');
    }
}
