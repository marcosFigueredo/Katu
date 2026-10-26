<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaMembro;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedidoHomologacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_discente_propoe_banca_que_fica_proposta_e_fora_da_fila(): void
    {
        Mail::fake();
        [$discente, $secretaria, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho))
            ->assertRedirect(route('meu-trabalho.index'));

        $banca = Banca::where('trabalho_academico_id', $trabalho->id)->firstOrFail();
        $this->assertSame('proposta', $banca->homologacao_status);
        $this->assertFalse($banca->libera_envio_trabalho);
        $this->assertSame(1, (int) $banca->homologacao_tentativa);
        $this->assertNull($banca->documento_homologacao_id);
        Mail::assertNothingSent();

        $this->actingAs($secretaria)->get(route('bancas.homologacao'))
            ->assertOk()
            ->assertDontSee(route('bancas.homologar', $banca), false);
    }

    public function test_discente_envia_pdf_com_banca_proposta(): void
    {
        Storage::fake('local');
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trabalho_documentos', 1);
        $this->assertSame('proposta', Banca::firstOrFail()->homologacao_status);
    }

    public function test_envio_pdf_sem_banca_proposta_e_recusado(): void
    {
        Storage::fake('local');
        [$discente, , $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasErrors(['pdf' => 'Proponha a banca antes de enviar o trabalho.']);

        // Banca já na fila da Secretaria também não aceita novo arquivo.
        $banca = $this->proporBanca($discente, $trabalho);
        $banca->update(['homologacao_status' => 'aguardando']);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasErrors(['pdf' => 'Proponha a banca antes de enviar o trabalho.']);
        $this->assertDatabaseCount('trabalho_documentos', 0);
    }

    public function test_enviar_homologacao_coloca_banca_aguardando_com_documento(): void
    {
        Mail::fake();
        [$discente, $secretaria, $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $banca->refresh();
        $this->assertSame('aguardando', $banca->homologacao_status);
        $this->assertSame($documento->id, (int) $banca->documento_homologacao_id);
        $this->assertSame(1, (int) $banca->homologacao_tentativa);
        $this->assertFalse($banca->libera_envio_trabalho);
        Mail::assertNothingSent();

        $this->actingAs($secretaria)->get(route('bancas.homologacao'))
            ->assertOk()
            ->assertSee(route('bancas.homologar', $banca), false);
    }

    public function test_enviar_homologacao_exige_composicao_valida(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        BancaMembro::where('banca_id', $banca->id)->limit(1)->update(['ativo' => false]);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
        $this->assertNull($banca->fresh()->documento_homologacao_id);
    }

    public function test_enviar_homologacao_exige_orcid_do_discente(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $discente->discenteProfile->update(['orcid_url' => null]);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('orcid');

        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
    }

    public function test_enviar_homologacao_de_documento_alheio_e_proibido(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $outro = User::factory()->discente()->create();

        $this->actingAs($outro)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertStatus(403);
    }

    public function test_discente_nao_edita_banca_aguardando(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        // Em "proposta" o discente edita.
        $this->actingAs($discente)->get(route('bancas.edit', $banca))->assertOk();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $this->documento($trabalho, $discente)))
            ->assertSessionHasNoErrors();

        $this->actingAs($discente)->get(route('bancas.edit', $banca))->assertStatus(403);
        $this->actingAs($discente)->put(route('bancas.update', $banca), $this->dadosQualificacao($trabalho, local: 'Sala 9'))
            ->assertStatus(403);
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_reenvio_apos_devolucao_incrementa_tentativa(): void
    {
        [$discente, $secretaria, $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento));

        $this->actingAs($secretaria)->post(route('bancas.devolver', $banca), ['observacoes' => 'Ajuste o local.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('devolvida', $banca->fresh()->homologacao_status);

        // O discente corrige o mesmo pedido; a banca segue devolvida até o reenvio.
        $this->actingAs($discente)->put(route('bancas.update', $banca), $this->dadosQualificacao($trabalho, local: 'Sala 2'))
            ->assertRedirect(route('meu-trabalho.index'));
        $this->assertSame('devolvida', $banca->fresh()->homologacao_status);
        $this->assertSame('Sala 2', $banca->fresh()->local);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee('Ajuste o local.')
            ->assertSee('Enviar à homologação');

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();

        $banca->refresh();
        $this->assertSame('aguardando', $banca->homologacao_status);
        $this->assertSame(2, (int) $banca->homologacao_tentativa);
    }

    public function test_enviar_homologacao_com_banca_aguardando_e_recusado(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento));

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('homologacao');
        $this->assertSame(1, (int) $banca->fresh()->homologacao_tentativa);
    }

    public function test_tela_meu_trabalho_mostra_estado_do_pedido(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee('Proposta — envie o trabalho')
            ->assertDontSee('Enviar à homologação');

        $this->documento($trabalho, $discente);
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee('Enviar à homologação');

        $banca->update(['homologacao_status' => 'aguardando']);
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee('Aguardando homologação')
            ->assertDontSee('Enviar à homologação');
    }

    public function test_rota_enviar_banca_nao_existe_mais(): void
    {
        $this->assertFalse(Route::has('meu-trabalho.enviar-banca'));
        $this->assertTrue(Route::has('meu-trabalho.enviar-homologacao'));
    }

    private function proporBanca(User $discente, TrabalhoAcademico $trabalho): Banca
    {
        $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho))
            ->assertSessionHasNoErrors();

        return Banca::where('trabalho_academico_id', $trabalho->id)->latest('id')->firstOrFail();
    }

    private function documento(TrabalhoAcademico $trabalho, User $discente): TrabalhoDocumento
    {
        return TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'enviado_por_id' => $discente->id,
            'tipo_documento' => 'qualificacao',
            'arquivo_pdf_path' => 'trabalhos/x.pdf',
            'arquivo_pdf_nome_original' => 'x.pdf',
            'enviado_em' => now(),
        ]);
    }

    private function dadosPdf(): array
    {
        return [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->createWithContent('trabalho.pdf', file_get_contents(base_path('docs/dissertacao.pdf'))),
        ];
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
        $secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $discenteUser = User::factory()->discente()->create(['primeiro_acesso' => false]);
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
}
