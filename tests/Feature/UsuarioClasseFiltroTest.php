<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioClasseFiltroTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_consulta_usuarios_por_classe(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        // 1. Docente interno coordenador
        $coordenador = User::factory()->create(['name' => 'Prof. Coordenador', 'categoria' => 'docente']);
        $docProfileCoord = DocenteProfile::create(['user_id' => $coordenador->id, 'titulacao' => 'Doutor']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfileCoord->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'pode_orientar' => true,
            'pode_avaliar' => true,
            'ativo' => true,
        ]);
        $programa->update(['coordenador_id' => $coordenador->id]);

        // 2. Docente interno comum
        $docenteInterno = User::factory()->create(['name' => 'Prof. Interno Comum', 'categoria' => 'docente']);
        $docProfileInt = DocenteProfile::create(['user_id' => $docenteInterno->id, 'titulacao' => 'Doutor']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfileInt->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'colaborador',
            'pode_orientar' => false,
            'pode_avaliar' => true,
            'ativo' => true,
        ]);

        // 3. Docente externo
        $docenteExterno = User::factory()->create(['name' => 'Prof. Convidado Externo', 'categoria' => 'docente']);
        $docProfileExt = DocenteProfile::create(['user_id' => $docenteExterno->id, 'titulacao' => 'Doutor', 'instituicao_origem' => 'USP']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfileExt->id,
            'tipo_vinculo' => 'externo',
            'pode_orientar' => false,
            'pode_avaliar' => true,
            'ativo' => true,
        ]);

        // 4. Discente
        $discente = User::factory()->create(['name' => 'Aluno Alguém', 'categoria' => 'discente']);
        $discProfile = DiscenteProfile::create(['user_id' => $discente->id]);
        DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $discProfile->id,
            'nivel' => 'mestrado',
            'ativo' => true,
        ]);

        // Teste classe = todos
        $response = $this->actingAs($secretaria)->get(route('usuarios.index'));
        $response->assertOk();
        $response->assertSee('Prof. Coordenador');
        $response->assertSee('Prof. Interno Comum');
        $response->assertSee('Prof. Convidado Externo');
        $response->assertSee('Aluno Alguém');

        // Teste classe = docentes (internos)
        $responseDoc = $this->actingAs($secretaria)->get(route('usuarios.index', ['classe' => 'docentes']));
        $responseDoc->assertOk();
        $responseDoc->assertSee('Prof. Interno Comum');
        $responseDoc->assertSee('Prof. Coordenador');
        $responseDoc->assertDontSee('Prof. Convidado Externo');
        $responseDoc->assertDontSee('Aluno Alguém');

        // Teste classe = discentes
        $responseDisc = $this->actingAs($secretaria)->get(route('usuarios.index', ['classe' => 'discentes']));
        $responseDisc->assertOk();
        $responseDisc->assertSee('Aluno Alguém');
        $responseDisc->assertDontSee('Prof. Interno Comum');
        $responseDisc->assertDontSee('Prof. Convidado Externo');

        // Teste classe = externos
        $responseExt = $this->actingAs($secretaria)->get(route('usuarios.index', ['classe' => 'externos']));
        $responseExt->assertOk();
        $responseExt->assertSee('Prof. Convidado Externo');
        $responseExt->assertDontSee('Prof. Interno Comum');
        $responseExt->assertDontSee('Aluno Alguém');

        // Teste classe = coordenador
        $responseCoord = $this->actingAs($secretaria)->get(route('usuarios.index', ['classe' => 'coordenador']));
        $responseCoord->assertOk();
        $responseCoord->assertSee('Prof. Coordenador');
        $responseCoord->assertDontSee('Prof. Interno Comum');
        $responseCoord->assertDontSee('Prof. Convidado Externo');
        $responseCoord->assertDontSee('Aluno Alguém');

        // Teste busca textual
        $responseBusca = $this->actingAs($secretaria)->get(route('usuarios.index', ['busca' => 'Convidado']));
        $responseBusca->assertOk();
        $responseBusca->assertSee('Prof. Convidado Externo');
        $responseBusca->assertDontSee('Prof. Interno Comum');
    }

    private function secretariaComPrograma(): array
    {
        $secretaria = User::factory()->create(['categoria' => 'sec']);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        \Illuminate\Support\Facades\DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$secretaria, $programa];
    }
}

