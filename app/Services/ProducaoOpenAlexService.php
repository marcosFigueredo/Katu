<?php

namespace App\Services;

use App\Models\ProducaoPublicacao;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Confere no OpenAlex se a publicação declarada pelo estudante é encontrada (pelo DOI; sem DOI, pelo título e ano).
 * Só informativo: "não encontrada" não invalida a produção e não entra na nota.
 */
class ProducaoOpenAlexService
{
    private const SIMILARIDADE_MINIMA = 90.0;

    public function verificar(ProducaoPublicacao $publicacao): void
    {
        try {
            $obra = $publicacao->doi ? $this->porDoi($publicacao->doi) : $this->porTitulo($publicacao->titulo, $publicacao->ano);
        } catch (\Throwable $e) {
            report($e);
            $publicacao->update(['openalex_situacao' => 'falha', 'openalex_verificada_em' => now()]);

            return;
        }

        $publicacao->update($obra === null
            ? ['openalex_situacao' => 'nao_encontrada', 'openalex_citacoes' => null, 'openalex_verificada_em' => now()]
            : [
                'openalex_situacao' => 'encontrada',
                'openalex_id' => $obra['id'] ?? $publicacao->openalex_id,
                'openalex_citacoes' => isset($obra['cited_by_count']) ? (int) $obra['cited_by_count'] : null,
                'openalex_verificada_em' => now(),
            ]);
    }

    public static function normalizarDoi(string $doi): string
    {
        return mb_strtolower(preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', trim($doi)));
    }

    private function porDoi(string $doi): ?array
    {
        $doi = self::normalizarDoi($doi);

        return $this->get('https://api.openalex.org/works/https://doi.org/'.str_replace('%2F', '/', rawurlencode($doi)));
    }

    /** Sem DOI: a obra com título quase igual (≥ 90%) e, havendo ano, no máximo um ano de diferença. */
    private function porTitulo(string $titulo, ?int $ano): ?array
    {
        $resposta = $this->get('https://api.openalex.org/works', ['search' => $titulo, 'per-page' => 5]);
        $alvo = $this->simplificar($titulo);

        foreach ((array) ($resposta['results'] ?? []) as $obra) {
            similar_text($alvo, $this->simplificar((string) ($obra['display_name'] ?? '')), $percentual);
            $anoObra = isset($obra['publication_year']) ? (int) $obra['publication_year'] : null;
            if ($percentual >= self::SIMILARIDADE_MINIMA && ($ano === null || $anoObra === null || abs($ano - $anoObra) <= 1)) {
                return $obra;
            }
        }

        return null;
    }

    /** JSON da resposta; null em 404; exceção em erro de rede ou do serviço. */
    private function get(string $url, array $query = []): ?array
    {
        $resposta = Http::connectTimeout(5)->timeout(15)
            ->withUserAgent('Katu/1.0 (mailto:'.config('mail.from.address').')')
            ->acceptJson()
            ->get($url, $query);

        if ($resposta->status() === 404) {
            return null;
        }
        if (! $resposta->successful() || ! is_array($resposta->json())) {
            throw new \RuntimeException('OpenAlex respondeu HTTP '.$resposta->status());
        }

        return $resposta->json();
    }

    private function simplificar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', Str::lower(Str::ascii($texto)))));
    }
}
