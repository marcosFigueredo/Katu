<?php

namespace App\Jobs;

use App\Models\ProgramaDocumentoReferencia;
use App\Services\IndexacaoBaseConhecimentoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class IndexarBaseConhecimentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(private readonly ProgramaDocumentoReferencia $documento)
    {
    }

    public function handle(IndexacaoBaseConhecimentoService $service): void
    {
        $this->documento->update(['status_indexacao' => 'processando']);

        try {
            $caminhoPdf = Storage::disk('local')->path($this->documento->arquivo_path);
            $paginas = (new Parser())->parseFile($caminhoPdf)->getPages();

            $textosPorPagina = [];
            foreach ($paginas as $i => $pagina) {
                $textosPorPagina[$i + 1] = trim($pagina->getText());
            }

            $service->indexar($this->documento, $textosPorPagina);

            $this->documento->update([
                'status_indexacao' => 'concluido',
                'indexado_em' => now(),
                'erro_indexacao' => null,
            ]);
        } catch (Throwable $e) {
            $this->documento->update([
                'status_indexacao' => 'falhou',
                'erro_indexacao' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }
}
