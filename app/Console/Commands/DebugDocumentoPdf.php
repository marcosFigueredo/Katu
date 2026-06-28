<?php

namespace App\Console\Commands;

use App\Models\TrabalhoDocumento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class DebugDocumentoPdf extends Command
{
    protected $signature = 'documento:debug-pdf {documentoId?}';

    protected $description = 'Mostra como o PDF está sendo lido pelo parser.';

    public function handle(): int
    {
        $documentoId = $this->argument('documentoId');

        $documento = $documentoId
            ? TrabalhoDocumento::with([
                'trabalhoAcademico.discenteVinculo.discenteProfile.user',
                'trabalhoAcademico.linhaPesquisa',
                'trabalhoAcademico.programa',
            ])->find($documentoId)
            : TrabalhoDocumento::with([
                'trabalhoAcademico.discenteVinculo.discenteProfile.user',
                'trabalhoAcademico.linhaPesquisa',
                'trabalhoAcademico.programa',
            ])->latest('id')->first();

        if (! $documento) {
            $this->error('Nenhum documento encontrado.');
            return self::FAILURE;
        }

        $usuario = $documento
            ->trabalhoAcademico
            ?->discenteVinculo
            ?->discenteProfile
            ?->user;

        $linhaPesquisa = $documento
            ->trabalhoAcademico
            ?->linhaPesquisa
            ?->nome;

        $this->info('Dados esperados pelo sistema');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Documento ID', $documento->id],
                ['Arquivo', $documento->arquivo_pdf_path],
                ['Discente esperado', $usuario?->name ?? 'não encontrado'],
                ['E-mail esperado', $usuario?->email ?? 'não encontrado'],
                ['Linha esperada', $linhaPesquisa ?? 'não encontrada'],
                ['Programa', $documento->trabalhoAcademico?->programa?->sigla ?? 'não encontrado'],
            ]
        );

        $caminhoPdf = Storage::disk('local')->path($documento->arquivo_pdf_path);

        if (! file_exists($caminhoPdf)) {
            $this->error("Arquivo físico não encontrado: {$caminhoPdf}");
            return self::FAILURE;
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($caminhoPdf);
        $paginas = $pdf->getPages();

        $this->line('');
        $this->info('Total de páginas detectadas: ' . count($paginas));

        foreach ([1, 2, 3] as $numeroPagina) {
            $indice = $numeroPagina - 1;

            if (! isset($paginas[$indice])) {
                $this->warn("Página {$numeroPagina} não existe.");
                continue;
            }

            $texto = trim($paginas[$indice]->getText());

            $this->line('');
            $this->line('============================================================');
            $this->info("PÁGINA {$numeroPagina} — TEXTO BRUTO EXTRAÍDO");
            $this->line('============================================================');
            $this->line($texto ?: '[sem texto extraído]');

            $linhas = collect(preg_split('/\R+/', $texto))
                ->map(fn ($linha) => trim($linha))
                ->filter()
                ->values();

            $this->line('');
            $this->info("PÁGINA {$numeroPagina} — LINHAS INTERPRETADAS");
            $this->table(
                ['#', 'Linha'],
                $linhas->map(fn ($linha, $i) => [
                    '#' => $i + 1,
                    'Linha' => $linha,
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }
}