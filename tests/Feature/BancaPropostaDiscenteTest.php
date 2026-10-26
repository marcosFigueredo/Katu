<?php

namespace Tests\Feature;

use App\Mail\BancaCadastradaMail;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BancaPropostaDiscenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_discente_propoe_banca_e_fica_proposta_sem_liberar_envio(): void
    {
        [$discente, , $trabalho] = $this->estrutura();

        $response = $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho));

        $response->assertRedirect(route('meu-trabalho.index'));
        $this->assertDatabaseHas('bancas', [
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'qualificacao',
            'homologacao_status' => 'proposta',
            'libera_envio_trabalho' => false,
            'homologada_por' => null,
        ]);
    }

    public function test_discente_nao_envia_email_ao_propor_banca(): void
    {
        Mail::fake();
        [$discente, , $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho));

        Mail::assertNothingSent();
    }

    public function test_discente_nao_pode_editar_banca_ja_homologada(): void
    {
        [$discente, , $trabalho, $banca] = $this->estruturaComBanca();
        $banca->update([
            'homologacao_status' => 'homologada',
            'homologada_por' => User::factory()->secretaria()->create()->id,
            'homologada_em' => now(),
            'libera_envio_trabalho' => true,
        ]);

        $this->actingAs($discente)->get(route('bancas.edit', $banca))->assertStatus(403);
        $this->actingAs($discente)->put(route('bancas.update', $banca), $this->dadosQualificacao($trabalho))
            ->assertStatus(403);
    }

    public function test_secretaria_homologa_pedido_do_discente_sem_banca_cadastrada_mail(): void
    {
        Mail::fake();
        [$discente, $secretaria, $trabalho, $banca] = $this->estruturaComBanca();
        $this->enviarPedido($discente, $trabalho);
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))->assertRedirect();

        $this->assertDatabaseHas('bancas', [
            'id' => $banca->id,
            'homologacao_status' => 'homologada',
            'homologada_por' => $secretaria->id,
            'libera_envio_trabalho' => true,
        ]);
        // Os e-mails da homologação única são definidos na Tarefa 11; o BancaCadastradaMail não sai mais.
        Mail::assertNotSent(BancaCadastradaMail::class);
    }

    public function test_discente_corrige_banca_devolvida_e_reenvia_para_homologacao(): void
    {
        [$discente, $secretaria, $trabalho, $banca] = $this->estruturaComBanca();
        $this->enviarPedido($discente, $trabalho);
        $this->actingAs($secretaria)->post(route('bancas.devolver', $banca), [
            'observacoes' => 'Ajuste o horário da banca.',
        ]);

        $response = $this->actingAs($discente)->put(route('bancas.update', $banca), $this->dadosQualificacao($trabalho, local: 'Sala 2'));

        // A correção mantém o pedido devolvido (com a não conformidade) até o reenvio.
        $response->assertRedirect(route('meu-trabalho.index'));
        $this->assertDatabaseHas('bancas', [
            'id' => $banca->id,
            'local' => 'Sala 2',
            'homologacao_status' => 'devolvida',
            'homologacao_observacoes' => 'Ajuste o horário da banca.',
            'libera_envio_trabalho' => false,
        ]);

        $this->enviarPedido($discente, $trabalho);

        $this->assertDatabaseHas('bancas', [
            'id' => $banca->id,
            'local' => 'Sala 2',
            'homologacao_status' => 'aguardando',
            'homologacao_tentativa' => 2,
            'libera_envio_trabalho' => false,
        ]);
    }

    private function enviarPedido(User $discente, TrabalhoAcademico $trabalho): void
    {
        $documento = TrabalhoDocumento::firstOrCreate(
            ['trabalho_academico_id' => $trabalho->id, 'tipo_documento' => 'qualificacao'],
            ['enviado_por_id' => $discente->id, 'arquivo_pdf_path' => 'x.pdf', 'arquivo_pdf_nome_original' => 'x.pdf'],
        );

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
    }

    public function test_discente_nao_acessa_index_nem_exclui_bancas(): void
    {
        [$discente, , , $banca] = $this->estruturaComBanca();

        $this->actingAs($discente)->get(route('bancas.index'))->assertStatus(403);
        $this->actingAs($discente)->delete(route('bancas.destroy', $banca))->assertStatus(403);
    }

    private function dadosQualificacao(TrabalhoAcademico $trabalho, string $local = 'Sala 1'): array
    {
        $avaliadores = DocenteProgramaVinculo::where('programa_id', $trabalho->programa_id)
            ->where('pode_avaliar', true)
            ->get();

        return [
            'tipo' => 'qualificacao',
            'local' => $local,
            'quantidade_avaliadores' => 2,
            'avaliador_interno_id' => $avaliadores->firstWhere('tipo_vinculo', 'interno')->id,
            'avaliador_externo_id' => $avaliadores->firstWhere('tipo_vinculo', 'externo')->id,
        ];
    }

    private function estrutura(): array
    {
        $secretaria = User::factory()->secretaria()->create();
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);
        \Illuminate\Support\Facades\DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $discenteUser = User::factory()->discente()->create();
        $profile = DiscenteProfile::create([
            'user_id' => $discenteUser->id,
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'ativo' => true,
        ]);
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

        $orientadorUser = User::factory()->docente()->create();
        $orientadorProfile = DocenteProfile::create([
            'user_id' => $orientadorUser->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UNEB',
            'instituicao_sigla' => 'UNEB',
            'ativo' => true,
        ]);
        $orientador = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $orientadorProfile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $trabalho->discente_programa_vinculo_id,
            'docente_programa_vinculo_id' => $orientador->id,
            'tipo' => 'orientador',
            'ordem' => 1,
            'ativo' => true,
        ]);

        foreach ([['interno', 'UNEB', 'UNEB'], ['externo', 'UFBA', 'UFBA']] as [$tipo, $sigla, $nome]) {
            $user = User::factory()->docente()->create();
            $docenteProfile = DocenteProfile::create([
                'user_id' => $user->id,
                'titulacao' => 'Doutorado',
                'instituicao_origem' => $nome,
                'instituicao_sigla' => $sigla,
                'ativo' => true,
            ]);
            DocenteProgramaVinculo::create([
                'programa_id' => $programa->id,
                'docente_profile_id' => $docenteProfile->id,
                'tipo_vinculo' => $tipo,
                'pode_orientar' => false,
                'pode_avaliar' => true,
                'ativo' => true,
            ]);
        }

        return [$discenteUser, $secretaria, $trabalho->fresh()];
    }

    private function estruturaComBanca(): array
    {
        [$discente, $secretaria, $trabalho] = $this->estrutura();

        $response = $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho));
        $response->assertSessionHasNoErrors();

        $banca = Banca::where('trabalho_academico_id', $trabalho->id)->firstOrFail();

        return [$discente, $secretaria, $trabalho->fresh(), $banca];
    }
}
