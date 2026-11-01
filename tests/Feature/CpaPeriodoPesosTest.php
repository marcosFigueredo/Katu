<?php

namespace Tests\Feature;

use App\Models\DimensaoQualidade;
use App\Models\Instituicao;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\PesoQualidade;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpaPeriodoPesosTest extends TestCase
{
    use RefreshDatabase;

    private Programa $programa;
    private User $membro;

    protected function setUp(): void
    {
        parent::setUp();

        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id]);
        $this->membro = $this->membroCpa($this->programa);
    }

    private function membroCpa(Programa $programa, array $extra = []): User
    {
        $user = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $programa->id, 'user_id' => $user->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato 1', ...$extra]);

        return $user;
    }

    /** O quadriênio é cadastrado pela Secretaria (decisão de 2026-11-01); aqui direto no banco. */
    private function periodo(array $extra = []): PeriodoQualidade
    {
        return PeriodoQualidade::create([
            'programa_id' => $this->programa->id, 'nome' => 'Quadriênio 2025–2028', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31', ...$extra,
        ]);
    }

    private function swaraDimensoes(array $s): array
    {
        $ordem = [];
        $sj = [];
        foreach (array_column(DimensaoQualidade::PADRAO, 'codigo') as $i => $codigo) {
            $ordem[$codigo] = $i + 1;
            $sj[$codigo] = $s[$codigo] ?? 0.10;
        }

        return ['ordem' => $ordem, 's' => $sj];
    }

    public function test_membro_ve_quadrienio_com_faixas_padrao(): void
    {
        $periodo = $this->periodo();

        $this->assertEquals(8.5, $periodo->fresh()->corte_alta);
        $this->assertEquals(4.0, $periodo->fresh()->corte_pouca);
        $this->actingAs($this->membro)->get(route('cpa.periodos.index'))->assertOk()->assertSee('Quadriênio 2025–2028');
    }

    public function test_faixas_fora_de_ordem_sao_recusadas(): void
    {
        $periodo = $this->periodo();

        $this->actingAs($this->membro)->put(route('cpa.periodos.update', $periodo), ['corte_alta' => 7, 'corte_boa' => 8, 'corte_suficiente' => 5.5, 'corte_pouca' => 4])
            ->assertSessionHasErrors('corte_alta');
        $this->assertEquals(8.5, $periodo->fresh()->corte_alta);
    }

    public function test_swara_das_dimensoes_grava_pesos_que_somam_1(): void
    {
        $this->periodo();
        $periodo = PeriodoQualidade::firstOrFail();

        $this->actingAs($this->membro)->get(route('cpa.pesos.edit', [$periodo, 'dimensao']))->assertOk()->assertSee('Rigor científico');
        $this->actingAs($this->membro)->put(route('cpa.pesos.update', [$periodo, 'dimensao']), $this->swaraDimensoes(['rigor' => 0]))
            ->assertSessionHasNoErrors();

        $pesos = $periodo->fresh()->pesos('dimensao');
        $this->assertEqualsWithDelta(0.170404, $pesos['rigor'], 0.0001);
        $this->assertEqualsWithDelta(1.0, array_sum($pesos), 0.0001);
        $this->assertSame(8, PesoQualidade::where('grupo', 'dimensao')->count());
        $this->assertSame($this->membro->id, PesoQualidade::first()->definido_por);
    }

    public function test_ordem_repetida_ou_s_negativo_e_recusado(): void
    {
        $this->periodo();
        $periodo = PeriodoQualidade::firstOrFail();

        $dados = $this->swaraDimensoes([]);
        $dados['ordem']['originalidade'] = 1;
        $this->actingAs($this->membro)->put(route('cpa.pesos.update', [$periodo, 'dimensao']), $dados)->assertSessionHasErrors('ordem');

        $this->actingAs($this->membro)->put(route('cpa.pesos.update', [$periodo, 'dimensao']), $this->swaraDimensoes(['autonomia' => -0.2]))
            ->assertSessionHasErrors('s.autonomia');
        $this->assertSame(0, PesoQualidade::count());
    }

    public function test_sem_swara_pesos_iguais(): void
    {
        $this->periodo();
        $periodo = PeriodoQualidade::firstOrFail();

        $this->assertEqualsWithDelta(0.125, $periodo->pesos('dimensao')['rigor'], 0.000001);
        $this->assertCount(8, $periodo->pesos('dimensao'));
        $this->assertEqualsWithDelta(0.25, $periodo->pesos('fonte')['espelho'], 0.000001);
        $this->assertCount(4, $periodo->pesos('fonte'));
    }

    public function test_swara_das_fontes(): void
    {
        $this->periodo();
        $periodo = PeriodoQualidade::firstOrFail();

        $this->actingAs($this->membro)->put(route('cpa.pesos.update', [$periodo, 'fonte']), [
            'ordem' => ['trabalhos' => 1, 'autoavaliacao_orientador' => 2, 'avaliacao_orientando' => 3, 'espelho' => 4],
            's' => ['trabalhos' => 0, 'autoavaliacao_orientador' => 1, 'avaliacao_orientando' => 0, 'espelho' => 0],
        ])->assertSessionHasNoErrors();

        // q = 1, 0,5, 0,5, 0,5 → soma 2,5
        $this->assertEqualsWithDelta(0.4, $periodo->fresh()->pesos('fonte')['trabalhos'], 0.0001);
    }

    public function test_classe_pelas_faixas(): void
    {
        $periodo = new PeriodoQualidade(['corte_alta' => 8.5, 'corte_boa' => 7, 'corte_suficiente' => 5.5, 'corte_pouca' => 4]);

        $this->assertSame(1, $periodo->classe(8.5));
        $this->assertSame(2, $periodo->classe(8.49));
        $this->assertSame(2, $periodo->classe(7.0));
        $this->assertSame(3, $periodo->classe(5.5));
        $this->assertSame(4, $periodo->classe(4.0));
        $this->assertSame(5, $periodo->classe(3.99));
    }

    public function test_periodo_do_programa_pela_data(): void
    {
        $this->periodo();

        $this->assertNotNull(PeriodoQualidade::doPrograma($this->programa, new \DateTime('2026-06-01')));
        $this->assertNull(PeriodoQualidade::doPrograma($this->programa, new \DateTime('2030-01-01')));
    }

    public function test_nao_membro_e_membro_encerrado_recebem_403(): void
    {
        $encerrado = $this->membroCpa($this->programa, ['inicio_em' => today()->subYears(2), 'fim_em' => today()->subDay()]);

        $this->actingAs(User::factory()->docente()->create())->get(route('cpa.periodos.index'))->assertForbidden();
        $this->actingAs($encerrado)->get(route('cpa.periodos.index'))->assertForbidden();
        $this->actingAs(User::factory()->secretaria()->create())->get(route('cpa.index'))->assertForbidden();
    }

    public function test_membro_nao_mexe_em_periodo_de_outro_programa(): void
    {
        $outro = Programa::create(['nome' => 'Outro', 'sigla' => 'OUT', 'instituicao_id' => $this->programa->instituicao_id]);
        $periodo = PeriodoQualidade::create(['programa_id' => $outro->id, 'nome' => 'P', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);

        $this->actingAs($this->membro)->get(route('cpa.pesos.edit', [$periodo, 'dimensao']))->assertForbidden();
        $this->actingAs($this->membro)->put(route('cpa.periodos.update', $periodo), ['nome' => 'X', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31'])
            ->assertForbidden();
    }
}
