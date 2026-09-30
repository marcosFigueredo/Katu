<?php

namespace Tests\Unit;

use App\Services\EmbeddingOllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingOllamaServiceTest extends TestCase
{
    public function test_embedder_retorna_vetor_da_api_do_ollama(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]]),
        ]);

        $vetor = (new EmbeddingOllamaService())->embedder('texto de teste');

        $this->assertSame([0.1, 0.2, 0.3], $vetor);
    }

    public function test_embedder_lanca_excecao_em_erro_http(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response('erro', 500),
        ]);

        $this->expectException(\RuntimeException::class);

        (new EmbeddingOllamaService())->embedder('texto de teste');
    }

    public function test_embedder_lanca_excecao_quando_resposta_sem_embedding(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => []]),
        ]);

        $this->expectException(\RuntimeException::class);

        (new EmbeddingOllamaService())->embedder('texto de teste');
    }

    public function test_similaridade_cosseno_de_vetores_identicos_e_um(): void
    {
        $service = new EmbeddingOllamaService();

        $this->assertEqualsWithDelta(1.0, $service->similaridadeCosseno([1, 0, 0], [1, 0, 0]), 0.0001);
    }

    public function test_similaridade_cosseno_de_vetores_ortogonais_e_zero(): void
    {
        $service = new EmbeddingOllamaService();

        $this->assertEqualsWithDelta(0.0, $service->similaridadeCosseno([1, 0], [0, 1]), 0.0001);
    }

    public function test_similaridade_cosseno_com_vetor_vazio_retorna_zero(): void
    {
        $service = new EmbeddingOllamaService();

        $this->assertSame(0.0, $service->similaridadeCosseno([1, 2, 3], []));
    }
}
