<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoOrientador;
use App\Models\AvaliacaoOrientando;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Models\User;
use App\Services\IndiceQualidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class IndiceQualidadeProgramaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private Programa $programa;
    private Banca $banca;
    private User $orientador;
    private User $coorientador;
    private User $discente;
    private array $avaliadores;
    private Barema $barema;
    private BaremaItem $rigor;
    private PeriodoQualidade $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->programa, $this->banca, $this->discente, $this->orientador, $this->coorientador, , $this->avaliadores] = $this->criarCenarioCompleto('defesa', false);
        $this->banca->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada', 'data_hora' => '2026-05-10 14:00:00']);
        $this->banca->trabalhoAcademico->publicacoes()->delete();

        $this->barema = Barema::create(['programa_id' => $this->programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $secao = $this->barema->secoes()->create(['nome' => 'Seção', 'peso' => 1, 'ordem' => 1, 'ativo' => true]);
        $this->rigor = BaremaItem::create([
            'barema_secao_id' => $secao->id, 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo('rigor')->id,
            'nome' => 'Rigor', 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true,
        ]);
        $this->periodo = PeriodoQualidade::create(['programa_id' => $this->programa->id, 'nome' => 'Quadriênio', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);
    }

    private function notasBanca(Banca $banca, float $nota): void
    {
        foreach ($banca->avaliadores()->get() as $membro) {
            $avaliacao = BancaAvaliacao::create(['banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $this->barema->id, 'status' => 'enviada', 'enviada_em' => now()]);
            $avaliacao->notas()->create(['barema_item_id' => $this->rigor->id, 'dimensao_qualidade_id' => $this->rigor->dimensao_qualidade_id, 'nota' => $nota]);
        }
    }

    /** Segunda defesa finalizada no mesmo programa e período, com os mesmos avaliadores. */
    private function outraDefesa(): Banca
    {
        $original = $this->banca->trabalhoAcademico;
        $profile = \App\Models\DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = $original->discenteVinculo->replicate();
        $vinculo->discente_profile_id = $profile->id;
        $vinculo->save();
        $trabalho = $original->replicate();
        $trabalho->discente_programa_vinculo_id = $vinculo->id;
        $trabalho->save();
        $outra = Banca::create(['trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa', 'status' => 'finalizada', 'resultado' => 'aprovado',
            'homologacao_status' => 'homologada', 'data_hora' => '2026-08-01 10:00:00']);
        foreach ($this->banca->avaliadores()->get() as $membro) {
            $outra->membros()->create(['docente_programa_vinculo_id' => $membro->docente_programa_vinculo_id, 'papel' => 'avaliador', 'ordem' => $membro->ordem, 'ativo' => true]);
        }

        return $outra;
    }

    private function autoavaliacao(Banca $banca, float $nota): void
    {
        $vinculo = $banca->trabalhoAcademico->orientacoes()->where('tipo', 'orientador')->first()?->docente_programa_vinculo_id
            ?? $this->banca->trabalhoAcademico->orientacoes()->where('tipo', 'orientador')->first()->docente_programa_vinculo_id;
        $auto = AutoavaliacaoOrientador::create(['banca_id' => $banca->id, 'docente_programa_vinculo_id' => $vinculo, 'barema_id' => $this->barema->id, 'nota_final' => $nota, 'enviada_em' => now()]);
        $auto->notas()->create(['barema_item_id' => $this->rigor->id, 'dimensao_qualidade_id' => $this->rigor->dimensao_qualidade_id, 'nota' => $nota]);
    }

    private function membroCpa(): User
    {
        $user = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $user->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato 1']);

        return $user;
    }

    public function test_indice_do_programa_combina_fontes_com_pesos(): void
    {
        $this->notasBanca($this->banca, 8);
        $outra = $this->outraDefesa();
        $this->notasBanca($outra, 6);
        $this->autoavaliacao($this->banca, 9);
        foreach (PeriodoQualidade::chaves('fonte') as $i => $chave) {
            $this->periodo->pesosQualidade()->create(['grupo' => 'fonte', 'chave' => $chave, 'ordem' => $i + 1, 's_j' => 0,
                'peso' => ['trabalhos' => 0.5, 'autoavaliacao_orientador' => 0.5][$chave] ?? 0.0001]);
        }

        $resultado = app(IndiceQualidadeService::class)->indicePrograma($this->periodo->fresh());

        $this->assertCount(2, $resultado['trabalhos']);
        $this->assertEqualsWithDelta(7.0, $resultado['fontes']['trabalhos']['rigor'], 0.001);
        $this->assertEqualsWithDelta(9.0, $resultado['fontes']['autoavaliacao_orientador']['rigor'], 0.001);
        $this->assertEqualsWithDelta(8.0, $resultado['dimensoes']['rigor'], 0.01);
        $this->assertNotNull($resultado['classe']);
    }

    public function test_fonte_sem_dados_fica_de_fora(): void
    {
        $this->notasBanca($this->banca, 8);

        $resultado = app(IndiceQualidadeService::class)->indicePrograma($this->periodo);

        $this->assertNull($resultado['fontes']['espelho']['rigor']);
        $this->assertEqualsWithDelta(8.0, $resultado['dimensoes']['rigor'], 0.001);
        $this->assertEqualsWithDelta(8.0, $resultado['indice'], 0.001);
    }

    public function test_avaliacao_do_orientando_entra_na_autonomia(): void
    {
        $this->notasBanca($this->banca, 8);
        $vinculo = $this->banca->trabalhoAcademico->orientacoes()->where('tipo', 'orientador')->first()->docente_programa_vinculo_id;
        $avaliacao = AvaliacaoOrientando::create(['banca_id' => $this->banca->id, 'docente_programa_vinculo_id' => $vinculo, 'enviada_em' => now()]);
        foreach (array_keys(AvaliacaoOrientando::ELEMENTOS) as $i => $elemento) {
            $avaliacao->notas()->create(['elemento' => $elemento, 'nota' => [6, 7, 8, 9, 10][$i], 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo('autonomia')->id]);
        }

        $resultado = app(IndiceQualidadeService::class)->indicePrograma($this->periodo);

        $this->assertEqualsWithDelta(8.0, $resultado['fontes']['avaliacao_orientando']['autonomia'], 0.001);
    }

    public function test_distribuicao_por_classe(): void
    {
        $this->notasBanca($this->banca, 9);
        $this->notasBanca($this->outraDefesa(), 3);

        $distribuicao = app(IndiceQualidadeService::class)->indicePrograma($this->periodo)['distribuicao'];

        $this->assertSame(1, $distribuicao[1]);
        $this->assertSame(1, $distribuicao[5]);
        $this->assertSame(0, $distribuicao[3]);
    }

    public function test_plagio_sai_de_todas_as_metricas_do_programa(): void
    {
        $this->notasBanca($this->banca, 9);
        $this->autoavaliacao($this->banca, 9);
        $outra = $this->outraDefesa();
        $this->notasBanca($outra, 6);
        $this->banca->update(['reprovada_por_plagio' => true, 'resultado' => 'reprovado']);

        $resultado = app(IndiceQualidadeService::class)->indicePrograma($this->periodo);

        $this->assertSame([$outra->id], array_keys($resultado['trabalhos']));
        $this->assertSame(1, array_sum($resultado['distribuicao']));
        $this->assertNull($resultado['fontes']['autoavaliacao_orientador']['rigor']);
        $this->assertEqualsWithDelta(6.0, $resultado['dimensoes']['rigor'], 0.001);
        $this->actingAs($this->membroCpa())->get(route('cpa.index'))->assertDontSee($this->discente->name);
    }

    public function test_cpa_ve_qualidade_do_programa(): void
    {
        $this->notasBanca($this->banca, 9);

        $this->actingAs($this->membroCpa())->get(route('cpa.index'))
            ->assertOk()
            ->assertSee('Alta qualidade')
            ->assertDontSee($this->discente->name)
            ->assertSee('Quadriênio');
    }

    public function test_secretaria_e_docente_sem_mandato_nao_veem(): void
    {
        $this->actingAs(User::factory()->secretaria()->create())->get(route('cpa.index'))->assertForbidden();
        $this->actingAs($this->orientador)->get(route('cpa.index'))->assertForbidden();
    }

    public function test_orientador_ve_nivel_do_trabalho_na_banca_finalizada(): void
    {
        $this->notasBanca($this->banca, 9);

        $this->actingAs($this->orientador)->get(route('avaliacoes.banca', $this->banca))
            ->assertOk()
            ->assertSee('Como a banca interpretou o trabalho')
            ->assertSee('Alta qualidade')
            ->assertSee('Rigor científico');
    }

    public function test_coorientador_avaliador_e_discente_nao_veem_o_nivel(): void
    {
        $this->notasBanca($this->banca, 9);

        $this->actingAs($this->coorientador)->get(route('avaliacoes.banca', $this->banca))->assertDontSee('Como a banca interpretou o trabalho');
        $this->actingAs($this->avaliadores[0])->get(route('avaliacoes.banca', $this->banca))->assertDontSee('Como a banca interpretou o trabalho');
        $this->actingAs($this->discente)->get(route('meu-trabalho.index'))->assertDontSee('Como a banca interpretou o trabalho');
    }
}
