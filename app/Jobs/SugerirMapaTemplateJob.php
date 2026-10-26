<?php

namespace App\Jobs;

use App\Models\ProgramaTemplateLatex;
use App\Services\Latex\ElementosVitais;
use App\Services\Latex\MapaTemplateOllamaService;
use App\Services\Latex\ProjetoLatexZipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Completa, com o Ollama, o mapa de elementos do template LaTeX do programa para os
 * códigos que as regras não resolveram no upload. Roda fora da requisição: o
 * template, o mapa por regras e o motor já estão gravados. Falha = mapa inalterado.
 */
class SugerirMapaTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly int $templateId,
        public readonly string $hash,
    ) {}

    public function handle(ProjetoLatexZipService $zip, MapaTemplateOllamaService $ollama): void
    {
        $template = ProgramaTemplateLatex::find($this->templateId);
        // template removido ou substituído por outro envio: nada a fazer
        if (! $template || $template->hash_sha256 !== $this->hash) {
            return;
        }

        $mapa = $template->mapa_elementos ?? [];
        $faltantes = array_values(array_diff(ElementosVitais::todos(), array_keys($mapa)));
        if ($faltantes === [] || ! Storage::disk('local')->exists($template->arquivo_path)) {
            return;
        }

        $tmp = storage_path('app/private/tmp/'.Str::uuid());
        File::ensureDirectoryExists($tmp);
        try {
            $fonte = $zip->extrair(Storage::disk('local')->path($template->arquivo_path), $tmp, $template->tex_principal)
                ->fonteExpandido();
        } catch (Throwable $e) {
            Log::warning("SugerirMapaTemplateJob: template {$template->id} não pôde ser lido: ".$e->getMessage());

            return;
        } finally {
            File::deleteDirectory($tmp);
        }

        $sugestoes = $ollama->sugerir($fonte, $faltantes);
        if ($sugestoes === []) {
            return;
        }

        // relê para não sobrescrever um reenvio feito enquanto o Ollama respondia
        $atual = ProgramaTemplateLatex::find($this->templateId);
        if (! $atual || $atual->hash_sha256 !== $this->hash) {
            return;
        }
        $atual->update(['mapa_elementos' => ($atual->mapa_elementos ?? []) + $sugestoes]);
    }
}
