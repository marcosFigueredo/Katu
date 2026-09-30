<?php

namespace Tests\Feature;

use App\Jobs\IndexarBaseConhecimentoJob;
use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Services\IndexacaoBaseConhecimentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndexarBaseConhecimentoJobTest extends TestCase
{
    use RefreshDatabase;

    private function documentoComPdfReal(): ProgramaDocumentoReferencia
    {
        Storage::fake('local');
        $programa = Programa::create(['nome' => 'PPG']);
        $caminho = 'normas-referencia/' . $programa->id . '/template.pdf';
        Storage::disk('local')->put($caminho, file_get_contents(base_path('docs/dissertacao.pdf')));

        return ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'template',
            'arquivo_path' => $caminho,
            'arquivo_nome_original' => 'dissertacao.pdf',
            'status_indexacao' => 'pendente',
        ]);
    }

    public function test_job_indexa_pdf_real_e_marca_concluido(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);
        $documento = $this->documentoComPdfReal();

        (new IndexarBaseConhecimentoJob($documento))->handle(app(IndexacaoBaseConhecimentoService::class));

        $documento->refresh();
        $this->assertSame('concluido', $documento->status_indexacao);
        $this->assertNotNull($documento->indexado_em);
        $this->assertNull($documento->erro_indexacao);
        $this->assertGreaterThan(0, BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->count());
    }

    public function test_job_marca_falhou_quando_ollama_esta_indisponivel(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response('indisponível', 500),
        ]);
        $documento = $this->documentoComPdfReal();

        (new IndexarBaseConhecimentoJob($documento))->handle(app(IndexacaoBaseConhecimentoService::class));

        $documento->refresh();
        $this->assertSame('falhou', $documento->status_indexacao);
        $this->assertNotNull($documento->erro_indexacao);
        $this->assertSame(0, BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->count());
    }

    public function test_failed_marca_documento_como_falhou_quando_o_job_e_matado_pela_fila(): void
    {
        $documento = $this->documentoComPdfReal();
        $documento->update(['status_indexacao' => 'processando']);

        (new IndexarBaseConhecimentoJob($documento))->failed(new \RuntimeException('tempo limite excedido'));

        $documento->refresh();
        $this->assertSame('falhou', $documento->status_indexacao);
        $this->assertStringContainsString('tempo limite excedido', $documento->erro_indexacao);
    }
}
