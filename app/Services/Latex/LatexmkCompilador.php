<?php

namespace App\Services\Latex;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class LatexmkCompilador implements CompiladorLatex
{
    private const MAX_LINHAS_LOG = 80;

    public function __construct(
        private readonly string $binario = 'latexmk',
        private readonly int $timeout = 180,
    ) {}

    public function compilar(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, ?string $apendiceTex = null): ResultadoCompilacao
    {
        $outdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexout_'.bin2hex(random_bytes(8));
        if (! @mkdir($outdir, 0777, true) && ! is_dir($outdir)) {
            return new ResultadoCompilacao(false, null, null, 'Não foi possível criar o diretório temporário de compilação.');
        }

        $apendiceArquivo = null;
        if ($apendiceTex !== null) {
            $apendiceArquivo = $outdir.DIRECTORY_SEPARATOR.'apendice_verificacao.tex';
            file_put_contents($apendiceArquivo, $apendiceTex);
        }

        $processo = new Process(
            $this->montarComando($projeto, $motor, $numerarLinhas, $outdir, $apendiceArquivo),
            $projeto->raiz,
            ['openout_any' => 'p', 'openin_any' => 'p'],
        );
        $processo->setTimeout($this->timeout);
        $processo->setInput(null);

        try {
            $processo->run();
        } catch (ProcessTimedOutException) {
            $this->encerrarArvore($processo);

            return new ResultadoCompilacao(false, null, null, "Tempo limite de compilação excedido ({$this->timeout} s).");
        } catch (\Throwable $e) {
            return new ResultadoCompilacao(false, null, null, 'Falha ao executar o latexmk: '.$e->getMessage());
        }

        $base = pathinfo($projeto->texPrincipal, PATHINFO_FILENAME);
        $pdf = $outdir.DIRECTORY_SEPARATOR.$base.'.pdf';
        $bbl = $outdir.DIRECTORY_SEPARATOR.$base.'.bbl';

        $log = (string) @file_get_contents($outdir.DIRECTORY_SEPARATOR.$base.'.log');
        if ($log === '') {
            $log = $processo->getOutput()."\n".$processo->getErrorOutput();
        }

        // sucesso = PDF gerado: o latexmk sai com código != 0 também por problemas só de
        // bibliografia (ex.: .bib ausente), que aparecem no log resumido como avisos.
        return $this->montarResultado(
            is_file($pdf),
            is_file($pdf) ? $pdf : null,
            is_file($bbl) ? $bbl : null,
            $log,
        );
    }

    /** @return list<string> */
    public function montarComando(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, string $outdir, ?string $apendiceArquivo = null): array
    {
        $flagMotor = match ($motor) {
            'xelatex' => '-pdfxe',
            'lualatex' => '-pdflua',
            default => '-pdf',
        };

        $pretex = '';
        if ($numerarLinhas) {
            // lineno só no início do documento: carregá-lo antes de \documentclass conflita
            // com classes que definem \linenumberfont (ex.: classPPGMSB/memoir).
            $pretex .= '\AtBeginDocument{\RequirePackage{lineno}\linenumbers}';
        }
        if ($apendiceArquivo !== null) {
            $caminho = str_replace(chr(92), '/', $apendiceArquivo);
            $pretex .= '\AtEndDocument{\clearpage\input{'.$caminho.'}}';
        }

        // -jobname fixo: com -usepretex o TeX recebe código na linha de comando e,
        // sem isso, nomearia a saída "texput".
        $cmd = [
            $this->binario, $flagMotor, '-no-shell-escape', '-interaction=nonstopmode', '-halt-on-error',
            '-jobname='.pathinfo($projeto->texPrincipal, PATHINFO_FILENAME),
            '-outdir='.$outdir,
        ];
        if ($pretex !== '') {
            // No Windows o latexmk descarta as barras invertidas do pretex a menos que
            // ele venha entre aspas literais (verificado com latexmk 4.88 / MiKTeX).
            $cmd[] = '-usepretex='.(PHP_OS_FAMILY === 'Windows' ? '"'.$pretex.'"' : $pretex);
        }
        $cmd[] = $projeto->texPrincipal;

        return $cmd;
    }

    public function montarResultado(bool $sucesso, ?string $pdf, ?string $bbl, string $log): ResultadoCompilacao
    {
        $linhas = preg_split('/\R/', $log) ?: [];
        $resumo = [];
        $avisos = [];
        $contexto = 0;

        foreach ($linhas as $linha) {
            $incluir = false;
            if (str_starts_with($linha, '!')) {
                $incluir = true;
                $contexto = 3;
            } elseif ($contexto > 0 && preg_match('/^l\.\d+/', $linha)) {
                $incluir = true;
                $contexto = 0;
            } elseif (preg_match('/^(LaTeX Warning|Class .* Warning|Package .* Warning|Warning--)/', $linha)) {
                $incluir = true;
                $contexto = 0;
            } elseif ($contexto > 0) {
                $contexto--;
            }

            if (preg_match('/^Class .* Warning/', $linha)) {
                $avisos[] = trim($linha);
            }
            if ($incluir && count($resumo) < self::MAX_LINHAS_LOG) {
                $resumo[] = $linha;
            }
        }

        return new ResultadoCompilacao($sucesso, $pdf, $bbl, implode("\n", $resumo), $avisos);
    }

    private function encerrarArvore(Process $processo): void
    {
        $pid = $processo->getPid();
        if ($pid && PHP_OS_FAMILY === 'Windows') {
            @exec('taskkill /F /T /PID '.(int) $pid.' 2>&1');
        }
        try {
            $processo->stop(0);
        } catch (\Throwable) {
        }
    }
}
