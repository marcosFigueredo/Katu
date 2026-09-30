<?php

namespace App\Jobs;

use App\Models\DocumentoAnalise;
use App\Services\ConformidadeNormasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class AnalisarConformidadeRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(private readonly DocumentoAnalise $analise)
    {
    }

    public function handle(ConformidadeNormasService $service): void
    {
        $documento = $this->analise->trabalhoDocumento;
        $documento->load('trabalhoAcademico');

        $programaId = $documento->trabalhoAcademico?->programa_id;

        if (! $programaId) {
            return;
        }

        $caminhoPdf = Storage::disk('local')->path($documento->arquivo_pdf_path);
        $paginas = (new Parser())->parseFile($caminhoPdf)->getPages();

        $textosPorPagina = [];
        foreach ($paginas as $i => $pagina) {
            $textosPorPagina[$i + 1] = trim($pagina->getText());
        }

        $service->analisar($this->analise, $programaId, $textosPorPagina);
    }
}
