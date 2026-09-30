<?php

namespace Tests\Feature;

use App\Jobs\IndexarBaseConhecimentoJob;
use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramaDocumentoReferenciaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function secretariaComPrograma(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        return [$sec, $programa];
    }

    public function test_secretaria_envia_documento_de_normas_e_despacha_indexacao(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('normas.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('programa-documentos-referencia.index'));

        $documento = ProgramaDocumentoReferencia::firstOrFail();
        $this->assertSame('normas', $documento->tipo);
        $this->assertSame('pendente', $documento->status_indexacao);
        Storage::disk('local')->assertExists($documento->arquivo_path);
        Queue::assertPushed(IndexarBaseConhecimentoJob::class);
    }

    public function test_reenviar_documento_substitui_o_anterior_e_apaga_arquivo_antigo(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$sec, $programa] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
        ]);
        $primeiro = ProgramaDocumentoReferencia::firstOrFail();
        $caminhoAntigo = $primeiro->arquivo_path;

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame(1, ProgramaDocumentoReferencia::where('programa_id', $programa->id)->where('tipo', 'normas')->count());
        $atual = ProgramaDocumentoReferencia::where('programa_id', $programa->id)->where('tipo', 'normas')->firstOrFail();
        $this->assertSame('v2.pdf', $atual->arquivo_nome_original);
        $this->assertSame($caminhoAntigo, $atual->arquivo_path);
        Storage::disk('local')->assertExists($atual->arquivo_path);
    }

    public function test_reenviar_documento_apaga_chunks_antigos_imediatamente(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$sec, $programa] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
        ]);
        $documento = ProgramaDocumentoReferencia::firstOrFail();
        BaseConhecimentoChunk::create([
            'programa_documento_referencia_id' => $documento->id,
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'pagina' => 1,
            'ordem' => 0,
            'conteudo' => 'Trecho da versão anterior.',
            'embedding' => [0.1],
        ]);

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame(0, BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->count());
    }

    public function test_arquivo_nao_pdf_e_recusado(): void
    {
        Storage::fake('local');
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-documentos-referencia.store'), [
            'tipo' => 'normas',
            'arquivo' => UploadedFile::fake()->create('normas.docx', 100, 'application/msword'),
        ])->assertSessionHasErrors('arquivo');
        $this->assertDatabaseCount('programa_documentos_referencia', 0);
    }

    public function test_docente_nao_acessa_upload_de_documentos_de_referencia(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('programa-documentos-referencia.index'))->assertStatus(403);
    }

    public function test_tela_lista_normas_e_template_com_status(): void
    {
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->get(route('programa-documentos-referencia.index'))
            ->assertOk()
            ->assertSee('Normas e Procedimentos')
            ->assertSee('Template')
            ->assertSee('Não enviado');
    }
}
