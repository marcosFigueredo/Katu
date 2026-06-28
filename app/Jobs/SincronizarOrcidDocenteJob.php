<?php

namespace App\Jobs;

use App\Models\DocenteProfile;
use App\Services\OrcidService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SincronizarOrcidDocenteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(private readonly DocenteProfile $docente) {}

    public function handle(OrcidService $orcidService): void
    {
        if (empty($this->docente->orcid_url)) {
            return;
        }

        $orcidService->sincronizar($this->docente);
    }

    public function failed(Throwable $e): void
    {
        // Erros já são registrados em orcid_snapshots.mensagem_erro pelo OrcidService
    }
}
