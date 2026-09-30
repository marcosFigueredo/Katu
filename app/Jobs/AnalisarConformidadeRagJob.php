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
use Throwable;

class AnalisarConformidadeRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly DocumentoAnalise $analise)
    {
    }

    public function handle(ConformidadeNormasService $service): void
    {
        try {
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
        } catch (Throwable $e) {
            $service->marcarTodasComoFalhou($this->analise, 'Não foi possível analisar o manuscrito: ' . $e->getMessage());
        }
    }

    /**
     * Chamado pela fila quando o job falha de vez (timeout do processo, todas
     * as tentativas esgotadas) — cenário que não passa pelo catch de handle().
     */
    public function failed(?Throwable $exception): void
    {
        app(ConformidadeNormasService::class)->marcarTodasComoFalhou(
            $this->analise,
            'Falha ao processar a análise: ' . ($exception?->getMessage() ?? 'tempo limite excedido.'),
        );
    }
}
