<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrcidDiscenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_discente_informa_orcid_e_lattes_no_perfil(): void
    {
        $user = User::factory()->discente()->create(['primeiro_acesso' => false]);
        DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://orcid.org/0000-0002-1825-0097', $user->discenteProfile->fresh()->orcid_url);
    }

    public function test_orcid_invalido_e_recusado(): void
    {
        $user = User::factory()->discente()->create();
        DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'orcid_url' => '0000-0002',
        ])->assertSessionHasErrors('orcid_url');
    }

    public function test_envio_a_homologacao_sem_orcid_e_recusado(): void
    {
        $user = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG']);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $banca = Banca::create(['trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'cadastrada', 'homologacao_status' => 'proposta', 'libera_envio_trabalho' => false]);
        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id, 'enviado_por_id' => $user->id, 'tipo_documento' => 'qualificacao',
            'arquivo_pdf_path' => 'x.pdf', 'arquivo_pdf_nome_original' => 'x.pdf',
        ]);

        $this->actingAs($user)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('orcid');
        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
    }

    public function test_secretaria_cadastra_discente_sem_orcid_com_lattes_e_curso(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        $curso = app(CursoProgramaService::class)->criar($programa, 'doutorado', 'profissional');

        $this->actingAs($sec)->post(route('usuarios.store'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '2026001', 'programa_curso_id' => $curso->id,
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
            'orientador_vinculo_id' => $this->orientador($programa)->id,
        ])->assertSessionHasNoErrors();

        $usuario = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('completo', $usuario->cadastro_status);
        $this->assertDatabaseHas('discente_programa_vinculos', ['programa_curso_id' => $curso->id, 'nivel' => 'doutorado']);
    }

    public function test_discente_sem_lattes_e_recusado(): void
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        $curso = app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');

        $this->actingAs($sec)->post(route('usuarios.store'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '1', 'programa_curso_id' => $curso->id,
        ])->assertSessionHasErrors('lattes_url');
    }

    public function test_perfil_do_discente_exibe_campos_de_orcid_e_lattes(): void
    {
        $user = User::factory()->discente()->create(['primeiro_acesso' => false]);
        DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="orcid_url"', false)
            ->assertSee('name="lattes_url"', false);
    }

    public function test_dashboard_do_discente_alerta_orcid_ausente(): void
    {
        $user = User::factory()->discente()->create(['primeiro_acesso' => false]);
        DiscenteProfile::create(['user_id' => $user->id, 'ativo' => true]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Informe seu ORCID');
    }

    public function test_discente_de_curso_inativo_pode_ser_editado_sem_trocar_de_curso(): void
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        $curso = app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');
        $dados = [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '1', 'programa_curso_id' => $curso->id,
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
            'orientador_vinculo_id' => $this->orientador($programa)->id,
        ];
        $this->actingAs($sec)->post(route('usuarios.store'), $dados)->assertSessionHasNoErrors();
        $usuario = User::where('email', 'ana@example.com')->firstOrFail();
        $curso->update(['ativo' => false]);

        $this->actingAs($sec)->get(route('usuarios.edit', $usuario))
            ->assertOk()->assertSee('Mestrado Acadêmico (inativo)');
        $this->actingAs($sec)->put(route('usuarios.update', $usuario), $dados + ['telefone' => '(71) 9999-0000'])
            ->assertSessionHasNoErrors();

        $outro = app(CursoProgramaService::class)->criar($programa, 'doutorado', 'academico');
        $outro->update(['ativo' => false]);
        $this->actingAs($sec)->put(route('usuarios.update', $usuario), ['programa_curso_id' => $outro->id] + $dados)
            ->assertSessionHasErrors('programa_curso_id');
    }

    private function orientador(Programa $programa): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create(['user_id' => User::factory()->docente()->create()->id, 'ativo' => true]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => 'interno', 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }
}
