<?php

namespace App\Services\Latex;

use App\Exceptions\ProjetoLatexInvalidoException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class ProjetoLatexZipService
{
    private const MAX_ARQUIVOS = 2000;

    private const MAX_BYTES = 200 * 1024 * 1024;

    private const IGNORADOS = ['.ds_store', 'desktop.ini'];

    public function extrair(string $zipPath, string $destino, ?string $principalPreferido = null): ProjetoLatex
    {
        $zip = new ZipArchive;
        if (! is_file($zipPath) || $zip->open($zipPath) !== true) {
            throw new ProjetoLatexInvalidoException('O arquivo enviado não é um zip válido.');
        }

        try {
            $entradas = $this->validarEntradas($zip);

            if (! is_dir($destino) && ! @mkdir($destino, 0775, true) && ! is_dir($destino)) {
                throw new ProjetoLatexInvalidoException('Não foi possível preparar a pasta de extração.');
            }
            $destino = realpath($destino);

            $this->gravar($zip, $entradas, $destino);
        } finally {
            $zip->close();
        }

        return $this->localizarPrincipal($destino, $principalPreferido);
    }

    /**
     * Valida todos os nomes e limites antes de gravar qualquer coisa.
     *
     * @return array<int, array{nome:string, dir:bool}> índice no zip => caminho normalizado
     */
    private function validarEntradas(ZipArchive $zip): array
    {
        $entradas = [];
        $total = 0;
        $bytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $nome = str_replace('\\', '/', (string) $stat['name']);

            if ($nome === '' || str_contains($nome, "\0")
                || str_starts_with($nome, '/') || preg_match('/^[A-Za-z]:/', $nome)) {
                throw new ProjetoLatexInvalidoException('O zip contém caminho absoluto ou inválido: '.$this->exibir($nome));
            }
            $segmentos = explode('/', $nome);
            if (in_array('..', $segmentos, true)) {
                throw new ProjetoLatexInvalidoException('O zip contém caminho com "..": '.$this->exibir($nome));
            }
            if ($this->ignorado($segmentos)) {
                continue;
            }

            $dir = str_ends_with($nome, '/');
            $entradas[$i] = ['nome' => rtrim($nome, '/'), 'dir' => $dir];
            if (! $dir) {
                $total++;
                $bytes += (int) $stat['size'];
            }
            if ($total > self::MAX_ARQUIVOS) {
                throw new ProjetoLatexInvalidoException('O zip contém arquivos demais (máximo '.self::MAX_ARQUIVOS.').');
            }
            if ($bytes > self::MAX_BYTES) {
                throw new ProjetoLatexInvalidoException('O conteúdo descompactado excede 200 MB.');
            }
        }

        return $entradas;
    }

    private function gravar(ZipArchive $zip, array $entradas, string $destino): void
    {
        $gravados = 0;
        foreach ($entradas as $i => $e) {
            if ($e['nome'] === '') {
                continue;
            }
            $alvo = $destino.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $e['nome']);
            if ($e['dir']) {
                @mkdir($alvo, 0775, true);

                continue;
            }
            $pasta = dirname($alvo);
            if (! is_dir($pasta)) {
                @mkdir($pasta, 0775, true);
            }
            $in = $zip->getStream($zip->getNameIndex($i));
            $out = $in ? @fopen($alvo, 'wb') : false;
            if (! $in || ! $out) {
                throw new ProjetoLatexInvalidoException('Falha ao extrair '.$this->exibir($e['nome']).'.');
            }
            while (! feof($in)) {
                $bloco = (string) fread($in, 65536);
                $gravados += strlen($bloco);
                if ($gravados > self::MAX_BYTES) {
                    fclose($in);
                    fclose($out);
                    throw new ProjetoLatexInvalidoException('O conteúdo descompactado excede 200 MB.');
                }
                fwrite($out, $bloco);
            }
            fclose($in);
            fclose($out);
        }
    }

    private function ignorado(array $segmentos): bool
    {
        if (in_array('__MACOSX', $segmentos, true)) {
            return true;
        }
        $ultimo = strtolower((string) end($segmentos));

        return in_array($ultimo, self::IGNORADOS, true);
    }

    private function localizarPrincipal(string $destino, ?string $preferido): ProjetoLatex
    {
        $candidatos = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destino, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $arq) {
            if (! $arq->isFile() || strtolower($arq->getExtension()) !== 'tex') {
                continue;
            }
            if ($this->ehDocumentoPrincipal((string) file_get_contents($arq->getPathname()))) {
                $candidatos[] = $arq->getPathname();
            }
        }

        if (! $candidatos) {
            throw new ProjetoLatexInvalidoException('Nenhum arquivo .tex principal (com \\documentclass e \\begin{document}) foi encontrado no zip.');
        }
        if (count($candidatos) === 1) {
            return $this->montar($candidatos[0]);
        }

        $ordem = array_values(array_filter([$preferido, 'main.tex', 'dissertacao.tex', 'tese.tex']));
        foreach ($ordem as $nome) {
            $achados = array_values(array_filter($candidatos, fn ($c) => strcasecmp(basename($c), $nome) === 0));
            if (count($achados) === 1) {
                return $this->montar($achados[0]);
            }
        }

        $nomes = implode(', ', array_map(fn ($c) => basename($c), $candidatos));
        throw new ProjetoLatexInvalidoException("Há mais de um arquivo .tex principal no zip ($nomes). Mantenha apenas um ou use o nome padrão (main.tex).");
    }

    private function montar(string $caminho): ProjetoLatex
    {
        return new ProjetoLatex(dirname($caminho), basename($caminho));
    }

    private function ehDocumentoPrincipal(string $conteudo): bool
    {
        $sem = preg_replace('/(?<!\\\\)%.*$/m', '', $conteudo);

        return str_contains($sem, '\\documentclass') && str_contains($sem, '\\begin{document}');
    }

    private function exibir(string $nome): string
    {
        return mb_substr(preg_replace('/\p{C}/u', '?', $nome), 0, 80);
    }
}
