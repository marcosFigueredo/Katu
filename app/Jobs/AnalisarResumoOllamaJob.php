<?php

namespace App\Jobs;

use App\Models\DocumentoAnalise;
use App\Services\OllamaResumoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class AnalisarResumoOllamaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries   = 1;

    public function __construct(private readonly DocumentoAnalise $analise) {}

    public function handle(OllamaResumoService $service): void
    {
        $documento = $this->analise->trabalhoDocumento;
        $documento->load('trabalhoAcademico');

        $caminhoPdf = Storage::disk('local')->path($documento->arquivo_pdf_path);

        $paginas = (new Parser())->parseFile($caminhoPdf)->getPages();

        $textosPorPagina = [];
        foreach ($paginas as $i => $pagina) {
            $textosPorPagina[$i + 1] = trim($pagina->getText());
        }

        $resumo        = $documento->trabalhoAcademico?->resumo;
        $palavrasChave = $documento->trabalhoAcademico?->palavras_chave;

        $service->analisar($this->analise, $textosPorPagina, $resumo, $palavrasChave);
    }
}
