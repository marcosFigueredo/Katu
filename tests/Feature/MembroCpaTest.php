<?php

namespace Tests\Feature;

use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\MembroCpa;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembroCpaTest extends TestCase
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

    private function docente(?Programa $programa = null): User
    {
        $user = User::factory()->docente()->create(['primeiro_acesso' => false]);
        $profile = DocenteProfile::create(['user_id' => $user->id, 'instituicao_sigla' => 'UNEB', 'ativo' => true]);
        DocenteProgramaVinculo::create([
            'programa_id' => ($programa ?? $this->programa)->id, 'docente_profile_id' => $profile->id, 'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente', 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);

        return $user;
    }

    private function designar(User $docente, array $extra = [])
    {
        return $this->actingAs($this->sec)->post(route('membros-cpa.store'), [
            'user_id' => $docente->id, 'inicio_em' => today()->subMonth()->toDateString(),
            'numero_ato' => 'Portaria nº 10/2026', ...$extra,
        ]);
    }

    public function test_secretaria_designa_docente_com_mandato_e_ato(): void
    {
        $docente = $this->docente();

        $this->designar($docente, ['fim_em' => today()->addYear()->toDateString()])->assertSessionHasNoErrors();

        $membro = MembroCpa::firstOrFail();
        $this->assertSame($this->programa->id, $membro->programa_id);
        $this->assertSame('Portaria nº 10/2026', $membro->numero_ato);
        $this->assertSame($this->sec->id, $membro->registrado_por);
        $this->actingAs($this->sec)->get(route('membros-cpa.index'))->assertOk()
            ->assertSee($docente->name)->assertSee('Portaria nº 10/2026');
    }

    public function test_encerrar_mandato(): void
    {
        $docente = $this->docente();
        $this->designar($docente);

        $this->actingAs($this->sec)->post(route('membros-cpa.encerrar', MembroCpa::first()))->assertSessionHasNoErrors();

        $this->assertSame(today()->toDateString(), MembroCpa::first()->fim_em->toDateString());
    }

    public function test_so_docente_do_programa_pode_ser_membro(): void
    {
        $outro = Programa::create(['nome' => 'Outro', 'sigla' => 'OUT', 'instituicao_id' => $this->programa->instituicao_id]);

        $this->designar($this->docente($outro))->assertSessionHasErrors('user_id');
        $this->designar(User::factory()->discente()->create())->assertSessionHasErrors('user_id');
        $this->assertSame(0, MembroCpa::count());
    }

    public function test_secretaria_de_outro_programa_nao_encerra(): void
    {
        $this->designar($this->docente());
        $outra = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        app(ContextoSecretaria::class)->vincular($outra, Programa::create(['nome' => 'Outro', 'sigla' => 'OUT']));

        $this->actingAs($outra)->post(route('membros-cpa.encerrar', MembroCpa::first()))->assertForbidden();
        $this->assertNull(MembroCpa::first()->fim_em);
    }

    public function test_programa_cpa_so_com_mandato_vigente(): void
    {
        $vigente = $this->docente();
        $futuro = $this->docente();
        $encerrado = $this->docente();
        $this->designar($vigente);
        $this->designar($futuro, ['inicio_em' => today()->addMonth()->toDateString()]);
        $this->designar($encerrado, ['inicio_em' => today()->subYears(2)->toDateString(), 'fim_em' => today()->subDay()->toDateString()]);

        $this->assertTrue($vigente->programaCpa()?->is($this->programa));
        $this->assertNull($futuro->programaCpa());
        $this->assertNull($encerrado->programaCpa());
        $this->assertNull($this->docente()->programaCpa());
    }

    public function test_menu_cpa_aparece_so_para_membro_vigente(): void
    {
        $membro = $this->docente();
        $this->designar($membro);

        $this->actingAs($membro)->get(route('avaliacoes.index'))->assertSee(route('cpa.index'), false);
        $this->actingAs($this->docente())->get(route('avaliacoes.index'))->assertDontSee(route('cpa.index'), false);
    }

    public function test_docente_nao_designa(): void
    {
        $this->actingAs($this->docente())->get(route('membros-cpa.index'))->assertForbidden();
    }
}
