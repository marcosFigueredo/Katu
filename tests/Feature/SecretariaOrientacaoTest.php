<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariaOrientacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_salva_orientacao_com_vinculo_do_discente(): void
    {
        [$secretaria, $trabalho, $orientador] = $this->estrutura();

        $response = $this->actingAs($secretaria)->put(route('orientacoes.update', $trabalho), [
            'orientador_vinculo_id' => $orientador->id,
        ]);

        $response->assertRedirect(route('orientacoes.index'));

        $this->assertDatabaseHas('orientacoes', [
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $trabalho->discente_programa_vinculo_id,
            'docente_programa_vinculo_id' => $orientador->id,
            'tipo' => 'orientador',
        ]);
    }

    public function test_secretaria_nao_vincula_docente_de_outro_programa(): void
    {
        [$secretaria, $trabalho] = $this->estrutura();
        $outroPrograma = Programa::create(['nome' => 'Outro Programa', 'sigla' => 'OUTRO']);
        $docente = User::factory()->docente()->create();
        $profile = DocenteProfile::create([
            'user_id' => $docente->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'Outra instituição',
            'instituicao_sigla' => 'OUT',
            'ativo' => true,
        ]);
        $vinculo = DocenteProgramaVinculo::create([
            'programa_id' => $outroPrograma->id,
            'docente_profile_id' => $profile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => true,
            'ativo' => true,
        ]);

        $response = $this->actingAs($secretaria)->put(route('orientacoes.update', $trabalho), [
            'orientador_vinculo_id' => $vinculo->id,
        ]);

        $response->assertSessionHasErrors('orientador_vinculo_id');
        $this->assertDatabaseMissing('orientacoes', [
            'trabalho_academico_id' => $trabalho->id,
            'docente_programa_vinculo_id' => $vinculo->id,
        ]);
    }

    private function estrutura(): array
    {
        $secretaria = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        app(\App\Services\ContextoSecretaria::class)->vincular($secretaria, $programa);

        $discente = User::factory()->discente()->create();
        $discenteProfile = DiscenteProfile::create([
            'user_id' => $discente->id,
            'ativo' => true,
        ]);
        $discenteVinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $discenteProfile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $discenteVinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);

        $orientadorUser = User::factory()->docente()->create();
        $profile = DocenteProfile::create([
            'user_id' => $orientadorUser->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UNEB',
            'instituicao_sigla' => 'UNEB',
            'ativo' => true,
        ]);
        $orientador = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $profile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => true,
            'ativo' => true,
        ]);

        return [$secretaria, $trabalho, $orientador];
    }
}
