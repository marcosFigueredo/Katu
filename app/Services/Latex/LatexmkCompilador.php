<?php

namespace App\Services\Latex;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LatexmkCompilador implements CompiladorLatex
{
    private const MAX_LINHAS_LOG = 80;

    private const MAX_BYTES_VARREDURA = 20 * 1024 * 1024;

    private const EXTENSOES_VARRIDAS = ['tex', 'sty', 'cls', 'bib', 'bbx', 'cbx', 'def', 'cfg'];

    /** Comandos que leem arquivos: o(s) argumento(s) entre chaves são caminhos. */
    private const COMANDOS_ARQUIVO = [
        'input', 'include', 'InputIfFileExists', 'IfFileExists', 'includegraphics', 'includepdf',
        'lstinputlisting', 'verbatiminput', 'VerbatimInput', 'inputminted', 'addbibresource',
        'bibliography', 'import', 'subimport', 'usepackage', 'RequirePackage', 'documentclass',
        'LoadClass', 'LoadClassWithOptions', 'RequirePackageWithOptions',
    ];

    private readonly bool $windows;

    private readonly bool $temTimeoutExterno;

    public function __construct(
        private readonly string $binario = 'latexmk',
        private readonly int $timeout = 180,
        ?bool $windows = null,
        ?bool $temTimeoutExterno = null,
    ) {
        $this->windows = $windows ?? (PHP_OS_FAMILY === 'Windows');
        $this->temTimeoutExterno = $temTimeoutExterno
            ?? (! $this->windows && (new ExecutableFinder)->find('timeout') !== null);
    }

    public function compilar(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, ?string $apendiceTex = null): ResultadoCompilacao
    {
        $recusa = $this->validarPrincipal($projeto->texPrincipal)
            ?? $this->procurarRc($projeto->raiz)
            ?? $this->varrerLeituraExterna($projeto->raiz);
        if ($recusa !== null) {
            return new ResultadoCompilacao(false, null, null, $recusa);
        }

        $outdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexout_'.bin2hex(random_bytes(8));
        if (! @mkdir($outdir, 0777, true) && ! is_dir($outdir)) {
            return new ResultadoCompilacao(false, null, null, 'Não foi possível criar o diretório temporário de compilação.');
        }

        $apendiceArquivo = null;
        if ($apendiceTex !== null) {
            $apendiceArquivo = $outdir.DIRECTORY_SEPARATOR.'apendice_verificacao.tex';
            if (@file_put_contents($apendiceArquivo, $apendiceTex) === false) {
                $this->removerDiretorio($outdir);

                return new ResultadoCompilacao(false, null, null, 'Não foi possível gravar o apêndice de verificação para compilação.');
            }
        }

        $processo = new Process(
            $this->montarComando($projeto, $motor, $numerarLinhas, $outdir, $apendiceArquivo),
            $projeto->raiz,
            ['openout_any' => 'p', 'openin_any' => 'p', 'TEXMFOUTPUT' => $outdir],
        );
        // com o "timeout" externo, ele encerra o grupo de processos; o do PHP é só a rede de segurança
        $processo->setTimeout($this->temTimeoutExterno ? $this->timeout + 15 : $this->timeout);
        $processo->setInput(null);

        try {
            $processo->run();
        } catch (ProcessTimedOutException) {
            // o Symfony já encerrou o processo (taskkill /F /T no Windows)
            $this->removerDiretorio($outdir);

            return $this->resultadoTimeout();
        } catch (\Throwable $e) {
            $this->removerDiretorio($outdir);

            return new ResultadoCompilacao(false, null, null, 'Falha ao executar o latexmk: '.$e->getMessage());
        }

        if ($this->temTimeoutExterno && in_array($processo->getExitCode(), [124, 137], true)) {
            $this->removerDiretorio($outdir);

            return $this->resultadoTimeout();
        }

        $base = pathinfo($projeto->texPrincipal, PATHINFO_FILENAME);
        $pdf = $outdir.DIRECTORY_SEPARATOR.$base.'.pdf';
        $bbl = $outdir.DIRECTORY_SEPARATOR.$base.'.bbl';

        $arquivoLog = $outdir.DIRECTORY_SEPARATOR.$base.'.log';
        $log = is_file($arquivoLog) ? (string) file_get_contents($arquivoLog) : '';
        if ($log === '') {
            $log = $processo->getOutput()."\n".$processo->getErrorOutput();
        }

        return $this->montarResultado(
            is_file($pdf) ? $pdf : null,
            is_file($bbl) ? $bbl : null,
            $log,
            $outdir,
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

        // -norc: latexmkrc/.latexmkrc é Perl executado pelo latexmk, inclusive o do diretório do projeto.
        // -jobname fixo: com -usepretex o TeX recebe código na linha de comando e,
        // sem isso, nomearia a saída "texput".
        $cmd = [
            $this->binario, '-norc', $flagMotor, '-no-shell-escape', '-interaction=nonstopmode', '-halt-on-error',
            '-jobname='.pathinfo($projeto->texPrincipal, PATHINFO_FILENAME),
            '-outdir='.$outdir,
        ];
        if ($pretex !== '') {
            // No Windows o latexmk descarta as barras invertidas do pretex a menos que
            // ele venha entre aspas literais (verificado com latexmk 4.88 / MiKTeX).
            $cmd[] = '-usepretex='.($this->windows ? '"'.$pretex.'"' : $pretex);
        }
        $cmd[] = './'.str_replace(chr(92), '/', $projeto->texPrincipal);

        if ($this->temTimeoutExterno) {
            // GNU timeout cria um grupo de processos e o mata inteiro (latexmk + engine + bibtex).
            $cmd = array_merge(['timeout', '--kill-after=5', (string) $this->timeout], $cmd);
        }

        return $cmd;
    }

    /** O sucesso é derivado do log: PDF presente e nenhuma linha de erro do TeX ("! ..."). */
    public function montarResultado(?string $pdf, ?string $bbl, string $log, ?string $diretorioSaida = null): ResultadoCompilacao
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

        // Um PDF antigo de uma passada anterior sobrevive a um erro fatal numa passada posterior;
        // erros só de bibliografia (sem "! ") continuam permitidos.
        $sucesso = $pdf !== null && is_file($pdf) && ! preg_match('/^! /m', $log);

        return new ResultadoCompilacao($sucesso, $pdf, $bbl, implode("\n", $resumo), $avisos, $diretorioSaida);
    }

    private function resultadoTimeout(): ResultadoCompilacao
    {
        return new ResultadoCompilacao(false, null, null, "Tempo limite de compilação excedido ({$this->timeout} s).");
    }

    private function validarPrincipal(string $texPrincipal): ?string
    {
        $normalizado = str_replace(chr(92), '/', $texPrincipal);
        $base = '[A-Za-z0-9][A-Za-z0-9_.\-]*\.tex';
        if (! preg_match('#^(?:[A-Za-z0-9_][A-Za-z0-9_.\-]*/)*'.$base.'$#', $normalizado) || str_contains($normalizado, '..')) {
            return 'Nome do arquivo principal inválido para compilação ("'.$texPrincipal.'"): use apenas letras, números, "_", "-" e ".", sem espaços, e extensão .tex.';
        }

        return null;
    }

    private function procurarRc(string $raiz): ?string
    {
        foreach ($this->arquivos($raiz) as $arquivo) {
            $nome = strtolower($arquivo->getFilename());
            if ($nome === 'latexmkrc' || $nome === '.latexmkrc') {
                return 'O projeto contém um arquivo "'.$arquivo->getFilename().'" (configuração do latexmk), que não é permitido por segurança. Remova-o e envie novamente.';
            }
        }

        return null;
    }

    private function varrerLeituraExterna(string $raiz): ?string
    {
        $nomes = implode('|', self::COMANDOS_ARQUIVO);
        foreach ($this->arquivos($raiz) as $arquivo) {
            if (! in_array(strtolower($arquivo->getExtension()), self::EXTENSOES_VARRIDAS, true)
                || $arquivo->getSize() > self::MAX_BYTES_VARREDURA) {
                continue;
            }
            $conteudo = (string) @file_get_contents($arquivo->getPathname());
            $conteudo = preg_replace('/(?<!\\\\)%[^\r\n]*/', '', $conteudo) ?? $conteudo;

            $achados = [];
            // \cmd*[opções]{arg}{arg2}
            if (preg_match_all('/\\\\('.$nomes.')\*?\s*(?:\[[^\]]*\])?\s*\{([^}]*)\}(?:\s*\{([^}]*)\})?/', $conteudo, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    // o 2º argumento só é caminho em \import/\subimport
                    $args = in_array($x[1], ['import', 'subimport'], true) ? array_slice($x, 2) : [$x[2]];
                    foreach ($args as $arg) {
                        foreach (explode(',', $arg) as $item) {
                            $achados[] = [$x[1], trim($item)];
                        }
                    }
                }
            }
            // \input arquivo (forma primitiva) e \openin1=arquivo
            if (preg_match_all('/\\\\input\s+([^\s{}\\\\]+)/', $conteudo, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $achados[] = ['input', $x[1]];
                }
            }
            if (preg_match_all('/\\\\openin\s*\\\\?\w+\s*=?\s*\{?([^\s{}]+)/', $conteudo, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $achados[] = ['openin', $x[1]];
                }
            }

            foreach ($achados as [$comando, $caminho]) {
                if ($this->caminhoPerigoso($caminho)) {
                    $rel = ltrim(str_replace(chr(92), '/', substr($arquivo->getPathname(), strlen($raiz))), '/');

                    return 'O arquivo "'.$rel.'" usa \\'.$comando.' com caminho fora do projeto ("'.$caminho.'"), o que não é permitido. Use apenas caminhos relativos dentro do projeto.';
                }
            }
        }

        return null;
    }

    private function caminhoPerigoso(string $caminho): bool
    {
        // MiKTeX/kpathsea removem aspas ao resolver o caminho
        $caminho = trim(trim($caminho), '"'."'");
        if ($caminho === '') {
            return false;
        }

        return in_array($caminho[0], ['/', chr(92), '~'], true)
            || preg_match('/^[A-Za-z]:/', $caminho) === 1
            || str_contains($caminho, '..');
    }

    /** @return \Generator<\SplFileInfo> */
    private function arquivos(string $raiz): \Generator
    {
        if (! is_dir($raiz)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($it as $arquivo) {
            if ($arquivo->isFile() && ! $arquivo->isLink()) {
                yield $arquivo;
            }
        }
    }

    private function removerDiretorio(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($p) && ! is_link($p) ? $this->removerDiretorio($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
