<?php

namespace Tests\Feature;

use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsuarioCoordenadorAtribuicaoTest extends TestCase
{
    use RefreshDatabase;

    private function secretariaComPrograma(): array
    {
        $secretaria = User::factory()->create(['categoria' => 'sec']);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$secretaria, $programa];
    }

    public function test_create_e_edit_renderizam_campos_de_coordenacao(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $docente = User::factory()->create(['name' => 'Prof. Permanente', 'categoria' => 'docente']);
        $docProfile = DocenteProfile::create(['user_id' => $docente->id, 'titulacao' => 'Doutor(a)']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfile->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'pode_orientar' => true,
            'pode_avaliar' => true,
            'ativo' => true,
        ]);

        $responseCreate = $this->actingAs($secretaria)->get(route('usuarios.create'));
        $responseCreate->assertOk();
        $responseCreate->assertSee('Função de Coordenação no Programa');
        $responseCreate->assertSee('id="eh_coordenador"', false);
        $responseCreate->assertSee('id="eh_vice_coordenador"', false);
        $responseCreate->assertSee('Exclusivo: Permanente');

        $responseEdit = $this->actingAs($secretaria)->get(route('usuarios.edit', $docente));
        $responseEdit->assertOk();
        $responseEdit->assertSee('Função de Coordenação no Programa');
        $responseEdit->assertSee('id="eh_coordenador"', false);
        $responseEdit->assertSee('id="eh_vice_coordenador"', false);
        $responseEdit->assertSee('Exclusivo: Permanente');
    }

    public function test_atribuir_coordenador_ao_cadastrar_docente_permanente(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $response = $this->actingAs($secretaria)->post(route('usuarios.store'), [
            'name' => 'Prof. Novo Coordenador',
            'email' => 'novocoord@uneb.br',
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 1,
            'eh_vice_coordenador' => 0,
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $novoDocente = User::where('email', 'novocoord@uneb.br')->firstOrFail();
        $this->assertEquals($novoDocente->id, $programa->fresh()->coordenador_id);
        $this->assertNull($programa->fresh()->vice_coordenador_id);
    }

    public function test_atribuir_vice_coordenador_ao_cadastrar_docente_permanente(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $response = $this->actingAs($secretaria)->post(route('usuarios.store'), [
            'name' => 'Prof. Novo Vice',
            'email' => 'novovice@uneb.br',
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 0,
            'eh_vice_coordenador' => 1,
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $novoDocente = User::where('email', 'novovice@uneb.br')->firstOrFail();
        $this->assertNull($programa->fresh()->coordenador_id);
        $this->assertEquals($novoDocente->id, $programa->fresh()->vice_coordenador_id);
    }

    public function test_docente_permanente_substitui_coordenador_existente(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        // Docente A já é coordenador
        $docenteA = User::factory()->create(['name' => 'Prof. Titular Antigo', 'categoria' => 'docente']);
        $docProfileA = DocenteProfile::create(['user_id' => $docenteA->id, 'titulacao' => 'Doutor(a)']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfileA->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'ativo' => true,
        ]);
        $programa->update(['coordenador_id' => $docenteA->id]);

        // Docente B será o novo coordenador
        $docenteB = User::factory()->create(['name' => 'Prof. Novo Titular', 'categoria' => 'docente']);
        $docProfileB = DocenteProfile::create(['user_id' => $docenteB->id, 'titulacao' => 'Doutor(a)']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfileB->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'ativo' => true,
        ]);

        $response = $this->actingAs($secretaria)->put(route('usuarios.update', $docenteB), [
            'name' => $docenteB->name,
            'email' => $docenteB->email,
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 1,
            'eh_vice_coordenador' => 0,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $this->assertEquals($docenteB->id, $programa->fresh()->coordenador_id);
    }

    public function test_desmarcar_coordenador_remove_atribuicao(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $docente = User::factory()->create(['name' => 'Prof. Titular', 'categoria' => 'docente']);
        $docProfile = DocenteProfile::create(['user_id' => $docente->id, 'titulacao' => 'Doutor(a)']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfile->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'ativo' => true,
        ]);
        $programa->update(['coordenador_id' => $docente->id]);

        $response = $this->actingAs($secretaria)->put(route('usuarios.update', $docente), [
            'name' => $docente->name,
            'email' => $docente->email,
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 0,
            'eh_vice_coordenador' => 0,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $this->assertNull($programa->fresh()->coordenador_id);
    }

    public function test_nao_permite_marcar_coordenador_e_vice_simultaneamente(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $response = $this->actingAs($secretaria)->post(route('usuarios.store'), [
            'name' => 'Prof. Duplo Cargo',
            'email' => 'duplo@uneb.br',
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 1,
            'eh_vice_coordenador' => 1,
        ]);

        $response->assertSessionHasErrors('coordenacao');
        $this->assertDatabaseMissing('users', ['email' => 'duplo@uneb.br']);
    }

    public function test_docente_colaborador_ou_externo_nao_pode_ser_coordenador(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        // Tentativa 1: Docente colaborador
        $responseColab = $this->actingAs($secretaria)->post(route('usuarios.store'), [
            'name' => 'Prof. Colaborador',
            'email' => 'colab@uneb.br',
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'colaborador',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 1,
            'eh_vice_coordenador' => 0,
        ]);
        $responseColab->assertSessionHasErrors('coordenacao');

        // Tentativa 2: Docente externo
        $responseExt = $this->actingAs($secretaria)->post(route('usuarios.store'), [
            'name' => 'Prof. Externo',
            'email' => 'externo@usp.br',
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'externo',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 1,
            'eh_vice_coordenador' => 0,
        ]);
        $responseExt->assertSessionHasErrors('coordenacao');
    }

    public function test_ao_mudar_docente_coordenador_para_colaborador_perde_cargo(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();

        $docente = User::factory()->create(['name' => 'Prof. Titular', 'categoria' => 'docente']);
        $docProfile = DocenteProfile::create(['user_id' => $docente->id, 'titulacao' => 'Doutor(a)']);
        DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $docProfile->id,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente',
            'ativo' => true,
        ]);
        $programa->update(['coordenador_id' => $docente->id]);

        // Secretária altera a categoria do docente de permanente para colaborador
        $response = $this->actingAs($secretaria)->put(route('usuarios.update', $docente), [
            'name' => $docente->name,
            'email' => $docente->email,
            'categoria' => 'docente',
            'vincular_programa' => 1,
            'tipo_vinculo' => 'interno',
            'categoria_docente' => 'colaborador',
            'titulacao' => 'Doutor(a)',
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'eh_coordenador' => 0,
            'eh_vice_coordenador' => 0,
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $this->assertNull($programa->fresh()->coordenador_id);
    }
}

