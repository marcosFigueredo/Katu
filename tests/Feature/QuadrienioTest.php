<?php

namespace Tests\Feature;

use App\Models\Instituicao;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QuadrienioTest extends TestCase
{
    use RefreshDatabase;

    private User $sec;
    private Programa $programa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id]);
        app(ContextoSecretaria::class)->vincular($this->sec, $this->programa);
    }

    private function cadastrar(array $extra = [])
    {
        return $this->actingAs($this->sec)->post(route('quadrienios.store'), [
            'nome' => 'Quadriênio 2025–2028', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31', ...$extra,
        ]);
    }

    public function test_secretaria_cadastra_quadrienio(): void
    {
        $this->cadastrar()->assertSessionHasNoErrors();

        $periodo = PeriodoQualidade::firstOrFail();
        $this->assertSame($this->programa->id, $periodo->programa_id);
        $this->assertEquals(8.5, $periodo->corte_alta);
        $this->actingAs($this->sec)->get(route('quadrienios.index'))->assertOk()->assertSee('Quadriênio 2025–2028');

        $this->actingAs($this->sec)->put(route('quadrienios.update', $periodo), ['nome' => 'Quadriênio 2025–2028 (CAPES)', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Quadriênio 2025–2028 (CAPES)', $periodo->fresh()->nome);
    }

    public function test_quadrienio_sobreposto_e_recusado(): void
    {
        $this->cadastrar();

        $this->cadastrar(['nome' => '2026', 'inicio_em' => '2026-01-01', 'fim_em' => '2026-12-31'])->assertSessionHasErrors('inicio_em');
        $this->cadastrar(['nome' => 'Próximo', 'inicio_em' => '2029-01-01', 'fim_em' => '2032-12-31'])->assertSessionHasNoErrors();
        $this->assertSame(2, PeriodoQualidade::count());
    }

    public function test_cpa_nao_cria_periodo(): void
    {
        $this->assertFalse(Route::has('cpa.periodos.store'));
    }

    public function test_cpa_ajusta_so_faixas(): void
    {
        $this->cadastrar();
        $periodo = PeriodoQualidade::firstOrFail();
        $membro = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $membro->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato']);

        $this->actingAs($membro)->put(route('cpa.periodos.update', $periodo), [
            'nome' => 'Outro nome', 'inicio_em' => '2020-01-01', 'fim_em' => '2021-12-31',
            'corte_alta' => 9, 'corte_boa' => 7.5, 'corte_suficiente' => 6, 'corte_pouca' => 4.5,
        ])->assertSessionHasNoErrors();

        $periodo->refresh();
        $this->assertSame('Quadriênio 2025–2028', $periodo->nome);
        $this->assertSame('2025-01-01', $periodo->inicio_em->toDateString());
        $this->assertEquals(9.0, $periodo->corte_alta);
    }

    public function test_docente_nao_cadastra_quadrienio(): void
    {
        $this->actingAs(User::factory()->docente()->create())->get(route('quadrienios.index'))->assertForbidden();
    }

    public function test_secretaria_de_outro_programa_nao_edita(): void
    {
        $this->cadastrar();
        $outra = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        app(ContextoSecretaria::class)->vincular($outra, Programa::create(['nome' => 'Outro', 'sigla' => 'OUT']));

        $this->actingAs($outra)->put(route('quadrienios.update', PeriodoQualidade::first()), ['nome' => 'X', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31'])
            ->assertForbidden();
    }
}
