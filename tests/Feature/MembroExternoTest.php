<?php

namespace Tests\Feature;

use App\Mail\ConviteMembroExternoMail;
use App\Models\DocenteProfile;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembroExternoTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_envia_convite_para_membro_externo(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        Mail::fake();

        $response = $this->actingAs($secretaria)->post(route('membros-externos.convites.store'), [
            'nome' => 'Dra. Maria Externa',
            'email' => 'maria.externa@example.com',
            'expira_em' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('membros-externos.index'));
        $this->assertDatabaseHas('convites_membros_externos', [
            'programa_id' => $programa->id,
            'email' => 'maria.externa@example.com',
            'status' => 'enviado',
        ]);
        Mail::assertSent(ConviteMembroExternoMail::class);
    }

    public function test_membro_externo_se_cadastra_com_diploma(): void
    {
        Storage::fake('local');
        $token = 'token-externo-valido';
        [$programa] = $this->secretariaComPrograma();
        DB::table('convites_membros_externos')->insert([
            'programa_id' => $programa->id,
            'criado_por' => User::where('categoria', 'sec')->first()->id,
            'nome' => 'Membro convidado',
            'email' => 'convidado@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'enviado',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/cadastro/membro-externo/'.$token, [
            'name' => 'Dra. Maria Convidada',
            'titulacao' => 'Doutorado',
            'area_formacao' => 'Saúde coletiva',
            'instituicao_origem' => 'Universidade Federal da Bahia',
            'instituicao_sigla' => 'UFBA',
            'orcid_url' => 'https://orcid.org/0000-0000-0000-0001',
            'diploma' => UploadedFile::fake()->image('diploma.png'),
        ]);

        $response->assertViewIs('membros-externos.pendente');
        $this->assertDatabaseHas('users', [
            'email' => 'convidado@example.com',
            'categoria' => 'docente',
            'cadastro_status' => 'aguardando_validacao',
        ]);
        $this->assertDatabaseHas('docente_profiles', [
            'diploma_doutorado_verificado' => false,
        ]);
        $this->assertDatabaseMissing('docente_programa_vinculos', [
            'tipo_vinculo' => 'externo',
        ]);
    }

    public function test_convite_expirado_nao_permite_cadastro(): void
    {
        $token = 'token-expirado';
        [$programa, $secretaria] = $this->secretariaComPrograma(true);
        DB::table('convites_membros_externos')->insert([
            'programa_id' => $programa->id,
            'criado_por' => $secretaria->id,
            'nome' => 'Membro expirado',
            'email' => 'expirado@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'enviado',
            'expires_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/cadastro/membro-externo/'.$token)->assertStatus(410);
    }

    public function test_secretaria_aprova_membro_e_cria_vinculo_externo(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $user = User::factory()->docente()->create([
            'email' => 'pendente@example.com',
            'cadastro_status' => 'aguardando_validacao',
            'primeiro_acesso' => true,
        ]);
        DocenteProfile::create([
            'user_id' => $user->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UFBA',
            'instituicao_sigla' => 'UFBA',
            'diploma_doutorado_path' => 'diplomas/teste.pdf',
            'diploma_doutorado_verificado' => false,
            'ativo' => true,
        ]);
        DB::table('convites_membros_externos')->insert([
            'programa_id' => $programa->id,
            'criado_por' => $secretaria->id,
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'token_hash' => hash('sha256', 'token-aprovacao'),
            'status' => 'aguardando_validacao',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Mail::fake();

        $response = $this->actingAs($secretaria)->post(route('membros-externos.aprovar', $user));

        $response->assertRedirect(route('membros-externos.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'cadastro_status' => 'completo',
        ]);
        $this->assertDatabaseHas('docente_programa_vinculos', [
            'programa_id' => $programa->id,
            'tipo_vinculo' => 'externo',
            'pode_avaliar' => true,
        ]);
        Mail::assertSent(\App\Mail\PrimeiroAcessoUsuarioMail::class);
    }

    public function test_docente_nao_acessa_gestao_de_membros_externos(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('membros-externos.index'))->assertStatus(403);
    }

    public function test_secretaria_devolve_cadastro_com_observacao(): void
    {
        [$secretaria, $programa] = $this->secretariaComPrograma();
        $user = User::factory()->docente()->create([
            'email' => 'devolver@example.com',
            'cadastro_status' => 'aguardando_validacao',
        ]);
        DocenteProfile::create([
            'user_id' => $user->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UFBA',
            'instituicao_sigla' => 'UFBA',
            'diploma_doutorado_path' => 'diplomas/teste.pdf',
            'ativo' => true,
        ]);
        DB::table('convites_membros_externos')->insert([
            'programa_id' => $programa->id,
            'criado_por' => $secretaria->id,
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'token_hash' => hash('sha256', 'token-devolucao'),
            'status' => 'aguardando_validacao',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($secretaria)
            ->post(route('membros-externos.devolver', $user), [
                'observacoes' => 'Envie uma imagem legível do diploma.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'cadastro_status' => 'devolvido',
        ]);
        $this->assertDatabaseHas('convites_membros_externos', [
            'user_id' => $user->id,
            'status' => 'devolvido',
            'observacoes' => 'Envie uma imagem legível do diploma.',
        ]);
    }

    private function secretariaComPrograma(bool $retornarProgramaPrimeiro = false): array
    {
        $secretaria = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $retornarProgramaPrimeiro
            ? [$programa, $secretaria]
            : [$secretaria, $programa];
    }
}
