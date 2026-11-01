<?php

namespace App\Jobs;

use App\Models\ProducaoPublicacao;
use App\Services\ProducaoOpenAlexService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Confere uma publicação declarada no OpenAlex, fora da requisição do estudante. */
class VerificarProducaoOpenAlexJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $publicacaoId)
    {
    }

    public function handle(ProducaoOpenAlexService $servico): void
    {
        $publicacao = ProducaoPublicacao::find($this->publicacaoId);
        if ($publicacao) {
            $servico->verificar($publicacao);
        }
    }
}
