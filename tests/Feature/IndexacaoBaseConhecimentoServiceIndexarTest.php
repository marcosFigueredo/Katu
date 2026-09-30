<?php

namespace Tests\Feature;

use App\Models\BaseConhecimentoChunk;
use App\Models\Programa;
use App\Models\ProgramaDocumentoReferencia;
use App\Services\EmbeddingOllamaService;
use App\Services\IndexacaoBaseConhecimentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndexacaoBaseConhecimentoServiceIndexarTest extends TestCase
{
    use RefreshDatabase;

    private function documento(): ProgramaDocumentoReferencia
    {
        $programa = Programa::create(['nome' => 'PPG']);

        return ProgramaDocumentoReferencia::create([
            'programa_id' => $programa->id,
            'tipo' => 'normas',
            'arquivo_path' => 'normas-referencia/1/normas.pdf',
            'arquivo_nome_original' => 'normas.pdf',
            'status_indexacao' => 'processando',
        ]);
    }

    public function test_indexa_chunks_com_embeddings_gerados(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $service->indexar($documento, [1 => 'A seção Aderência ao Programa é obrigatória.']);

        $chunks = BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->get();
        $this->assertCount(1, $chunks);
        $this->assertSame([0.1, 0.2, 0.3], $chunks->first()->embedding);
        $this->assertSame($documento->programa_id, $chunks->first()->programa_id);
        $this->assertSame('normas', $chunks->first()->tipo);
    }

    public function test_reindexar_substitui_chunks_antigos(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.5]]),
        ]);
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $service->indexar($documento, [1 => 'Conteúdo da primeira versão do documento.']);
        $this->assertSame(1, BaseConhecimentoChunk::count());

        $service->indexar($documento, [1 => 'Conteúdo totalmente novo.', 2 => 'Mais um trecho novo aqui.']);

        $this->assertSame(2, BaseConhecimentoChunk::count());
        $this->assertSame(
            ['Conteúdo totalmente novo.', 'Mais um trecho novo aqui.'],
            BaseConhecimentoChunk::orderBy('ordem')->pluck('conteudo')->all()
        );
    }

    public function test_lanca_excecao_quando_pdf_nao_tem_texto_extraivel(): void
    {
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $this->expectException(\RuntimeException::class);

        $service->indexar($documento, [1 => '   ', 2 => '']);
    }

    public function test_falha_de_um_chunk_nao_impede_os_demais(): void
    {
        $chamadas = 0;
        Http::fake(function () use (&$chamadas) {
            $chamadas++;

            return $chamadas === 1
                ? Http::response('erro', 500)
                : Http::response(['embedding' => [0.9]]);
        });
        $documento = $this->documento();
        $service = new IndexacaoBaseConhecimentoService(new EmbeddingOllamaService());

        $service->indexar($documento, [
            1 => str_repeat('Primeiro trecho bem distinto do documento. ', 20),
            2 => 'Segundo trecho, completamente separado.',
        ]);

        $this->assertGreaterThanOrEqual(1, BaseConhecimentoChunk::count());
    }
}
