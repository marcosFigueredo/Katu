<?php

namespace App\Console\Commands;

use App\Models\ProducaoPublicacao;
use App\Services\ProducaoOpenAlexService;
use Illuminate\Console\Command;

/** Confere no OpenAlex as publicações ainda não conferidas ou cuja consulta falhou. */
class VerificarProducaoOpenAlex extends Command
{
    protected $signature = 'producao:verificar-openalex';

    protected $description = 'Confere no OpenAlex as publicações declaradas pendentes ou com falha (informativo).';

    public function handle(ProducaoOpenAlexService $servico): int
    {
        $pendentes = ProducaoPublicacao::whereNull('openalex_situacao')->orWhere('openalex_situacao', 'falha')->get();

        foreach ($pendentes as $publicacao) {
            $servico->verificar($publicacao);
        }

        $this->info("{$pendentes->count()} publicação(ões) conferida(s).");

        return self::SUCCESS;
    }
}
