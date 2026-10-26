<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDiscenteOrientacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_mostra_orientador_com_email_e_coorientadores(): void
    {
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);

        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'orcid_url' => 'https://orcid.org/0000-0002-1825-0097', 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);

        $orientadorUser = User::factory()->docente()->create(['name' => 'Ana Orientadora', 'email' => 'ana.orientadora@uneb.br']);
        $orientadorProfile = DocenteProfile::create(['user_id' => $orientadorUser->id, 'titulacao' => 'Doutorado', 'ativo' => true]);
        $orientadorVinculo = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $orientadorProfile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $orientadorVinculo->id,
            'tipo' => 'orientador',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $coorientadorUser = User::factory()->docente()->create(['name' => 'Bruno Coorientador', 'email' => 'bruno.coorientador@uneb.br']);
        $coorientadorProfile = DocenteProfile::create(['user_id' => $coorientadorUser->id, 'titulacao' => 'Doutorado', 'ativo' => true]);
        $coorientadorVinculo = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $coorientadorProfile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $coorientadorVinculo->id,
            'tipo' => 'coorientador',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $this->actingAs($discente)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ana Orientadora')
            ->assertSee('ana.orientadora@uneb.br')
            ->assertSee('Bruno Coorientador');
    }

    public function test_dashboard_avisa_quando_nao_ha_orientador_definido(): void
    {
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);

        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'orcid_url' => 'https://orcid.org/0000-0002-1825-0097', 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);

        $this->actingAs($discente)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Orientador ainda não definido');
    }
}
