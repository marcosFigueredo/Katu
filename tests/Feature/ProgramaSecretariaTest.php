<?php

namespace Tests\Feature;

use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaSecretariaTest extends TestCase
{
    use RefreshDatabase;

    private function instituicao(): Instituicao
    {
        return Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
    }

    public function test_secretaria_sem_programa_cria_programa_vinculado(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();

        $this->actingAs($sec)->post(route('programas.store'), [
            'nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $inst->id,
            'conceito_capes' => 4, 'ano_inicio' => 2012,
        ])->assertRedirect();

        $programa = Programa::firstOrFail();
        $this->assertSame($inst->id, $programa->instituicao_id);
        $this->assertTrue(app(ContextoSecretaria::class)->programa($sec)->is($programa));
    }

    public function test_programa_exige_instituicao(): void
    {
        $sec = User::factory()->secretaria()->create();

        $this->actingAs($sec)->post(route('programas.store'), ['nome' => 'PPG'])
            ->assertSessionHasErrors('instituicao_id');
    }

    public function test_secretaria_com_programa_nao_cria_outro(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();
        $programa = Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->get(route('programas.create'))->assertRedirect(route('programas.edit', $programa));
        $this->actingAs($sec)->post(route('programas.store'), ['nome' => 'B', 'instituicao_id' => $inst->id])
            ->assertForbidden();
        $this->assertDatabaseCount('programas', 1);
    }

    public function test_secretaria_nao_edita_programa_de_outra(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();
        app(ContextoSecretaria::class)->vincular($sec, Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]));
        $outro = Programa::create(['nome' => 'B', 'instituicao_id' => $inst->id]);

        $this->actingAs($sec)->get(route('programas.edit', $outro))->assertForbidden();
        $this->actingAs($sec)->put(route('programas.update', $outro), ['nome' => 'X', 'instituicao_id' => $inst->id])
            ->assertForbidden();
    }

    public function test_coordenador_deve_ser_docente_permanente_do_programa_e_diferente_do_vice(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();
        $programa = Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $permanente = User::factory()->docente()->create();
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => DocenteProfile::create(['user_id' => $permanente->id, 'ativo' => true])->id,
            'tipo_vinculo' => 'interno', 'categoria_docente' => 'permanente', 'ativo' => true,
        ]);
        $estranho = User::factory()->docente()->create();

        $base = ['nome' => 'A', 'instituicao_id' => $inst->id];
        $this->actingAs($sec)->put(route('programas.update', $programa), $base + ['coordenador_id' => $estranho->id])
            ->assertSessionHasErrors('coordenador_id');
        $this->actingAs($sec)->put(route('programas.update', $programa), $base + [
            'coordenador_id' => $permanente->id, 'vice_coordenador_id' => $permanente->id,
        ])->assertSessionHasErrors('vice_coordenador_id');
        $this->actingAs($sec)->put(route('programas.update', $programa), $base + ['coordenador_id' => $permanente->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($permanente->id, $programa->fresh()->coordenador_id);
    }

    public function test_conceito_capes_fora_de_1_a_7_e_recusado(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = $this->instituicao();

        $this->actingAs($sec)->post(route('programas.store'), [
            'nome' => 'A', 'instituicao_id' => $inst->id, 'conceito_capes' => 8,
        ])->assertSessionHasErrors('conceito_capes');
    }

    public function test_dashboard_leva_secretaria_sem_programa_ao_cadastro(): void
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);

        $this->actingAs($sec)->get(route('dashboard'))->assertRedirect(route('programas.create'));
    }

    public function test_formularios_do_programa_exibem_campos_completos(): void
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $inst = $this->instituicao();

        $this->actingAs($sec)->get(route('programas.create'))
            ->assertOk()
            ->assertSee('name="instituicao_id"', false)
            ->assertSee(route('instituicoes.create'), false)
            ->assertSee('name="conceito_capes"', false)
            ->assertDontSee('name="oferta_mestrado"', false);

        $programa = Programa::create(['nome' => 'A', 'instituicao_id' => $inst->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->get(route('programas.edit', $programa))
            ->assertOk()
            ->assertSee('name="coordenador_id"', false)
            ->assertSee('name="vice_coordenador_id"', false)
            ->assertSee('name="logo"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_programa_e_menu_levam_as_areas_de_concentracao(): void
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => 'A', 'instituicao_id' => $this->instituicao()->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->get(route('programas.edit', $programa))
            ->assertOk()
            ->assertSee(route('programas.areas.index', $programa), false)
            ->assertSee('Áreas e linhas de pesquisa');
        $this->actingAs($sec)->get(route('programa-cursos.index'))
            ->assertSee(route('programas.areas.index', $programa), false);
    }
}
