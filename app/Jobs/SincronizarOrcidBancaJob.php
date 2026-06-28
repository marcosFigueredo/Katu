<?php

namespace App\Jobs;

use App\Models\Banca;
use App\Services\BancaIndicadorService;
use App\Services\OrcidService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SincronizarOrcidBancaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(private readonly Banca $banca) {}

    public function handle(OrcidService $orcidService, BancaIndicadorService $bancaIndicadorService): void
    {
        $this->banca->loadMissing([
            'membros.docenteProgramaVinculo.docenteProfile',
            'trabalhoAcademico.linhaPesquisa',
        ]);

        foreach ($this->banca->membros->where('papel', 'avaliador')->where('ativo', true) as $membro) {
            $docente = $membro->docenteProgramaVinculo?->docenteProfile;

            if ($docente && ! empty($docente->orcid_url)) {
                $orcidService->sincronizar($docente);
            }
        }

        $bancaIndicadorService->calcular($this->banca);
    }
}
