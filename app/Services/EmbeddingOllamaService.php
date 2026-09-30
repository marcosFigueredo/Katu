<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingOllamaService
{
    /**
     * Gera o embedding (vetor) de um texto via Ollama. Lança RuntimeException em falha —
     * quem chama decide como tratar (diferente de OllamaResumoService, que nunca lança).
     */
    public function embedder(string $texto): array
    {
        $response = Http::timeout(config('ollama.timeout'))
            ->post(config('ollama.url') . '/api/embeddings', [
                'model'  => config('ollama.embedding_model'),
                'prompt' => $texto,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ollama HTTP ' . $response->status() . ' ao gerar embedding: ' . mb_substr($response->body(), 0, 200)
            );
        }

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || empty($embedding)) {
            throw new RuntimeException('Ollama não retornou embedding válido.');
        }

        return $embedding;
    }

    public function similaridadeCosseno(array $a, array $b): float
    {
        $n = min(count($a), count($b));

        if ($n === 0) {
            return 0.0;
        }

        $produtoEscalar = 0.0;
        $normaA = 0.0;
        $normaB = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $produtoEscalar += $a[$i] * $b[$i];
            $normaA += $a[$i] ** 2;
            $normaB += $b[$i] ** 2;
        }

        if ($normaA <= 0.0 || $normaB <= 0.0) {
            return 0.0;
        }

        return $produtoEscalar / (sqrt($normaA) * sqrt($normaB));
    }
}
