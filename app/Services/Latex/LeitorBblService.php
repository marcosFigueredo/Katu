<?php

namespace App\Services\Latex;

use App\Support\NomePessoa;

/**
 * Lê o .bbl gerado pela compilação (bibtex: \bibitem; biblatex: \entry{chave}) e devolve
 * uma lista de referências com chave, texto limpo, DOI, ano e título (quando legíveis).
 */
class LeitorBblService
{
    private const REGEX_DOI = '/10\.\d{4,9}\/[^\s},]+/';

    /** @return list<array{chave: ?string, texto: string, doi: ?string, ano: ?int, titulo: ?string}> */
    public function ler(string $bblPath): array
    {
        $conteudo = is_file($bblPath) ? (string) @file_get_contents($bblPath) : '';
        if ($conteudo === '') {
            return [];
        }
        // comentários do .bbl (linhas iniciadas por %)
        $conteudo = preg_replace('/^\s*%.*$/m', '', $conteudo) ?? $conteudo;

        if (preg_match('/\\\\entry\{/', $conteudo)) {
            return $this->lerBiblatex($conteudo);
        }

        return $this->lerBibtex($conteudo);
    }

    /** @return list<array{chave: ?string, texto: string, doi: ?string, ano: ?int, titulo: ?string}> */
    private function lerBibtex(string $conteudo): array
    {
        $conteudo = preg_replace('/\\\\end\{thebibliography\}.*$/s', '', $conteudo) ?? $conteudo;
        $partes = preg_split('/\\\\bibitem\b/', $conteudo) ?: [];
        array_shift($partes); // o que vem antes do primeiro \bibitem

        $refs = [];
        foreach ($partes as $parte) {
            // [rótulo opcional]{chave}
            if (! preg_match('/^\s*(?:\[((?:[^\[\]{}]|\{(?:[^{}]|\{[^{}]*\})*\})*)\])?\s*\{([^}]*)\}(.*)$/s', $parte, $m)) {
                continue;
            }
            $rotulo = $m[1];
            $chave = trim($m[2]);
            $corpo = $m[3];

            $doi = $this->extrairDoi($corpo);
            $titulo = null;
            $blocos = preg_split('/\\\\newblock\b/', $corpo) ?: [];
            if (count($blocos) > 1) {
                $titulo = $this->limparTitulo($blocos[1]);
            }
            $texto = $this->limparTexto($corpo);
            $ano = $this->extrairAno($this->semDoi($corpo));
            if ($ano === null && $rotulo !== '') {
                $ano = $this->extrairAno($rotulo);
            }

            $refs[] = ['chave' => $chave, 'texto' => $texto, 'doi' => $doi, 'ano' => $ano, 'titulo' => $titulo];
        }

        return $refs;
    }

    /** @return list<array{chave: ?string, texto: string, doi: ?string, ano: ?int, titulo: ?string}> */
    private function lerBiblatex(string $conteudo): array
    {
        $refs = [];
        preg_match_all('/\\\\entry\{([^}]*)\}\{[^}]*\}\{[^}]*\}(.*?)\\\\endentry/s', $conteudo, $entradas, PREG_SET_ORDER);

        foreach ($entradas as $e) {
            $chave = trim($e[1]);
            $corpo = $e[2];

            $titulo = $this->campo($corpo, 'title');
            $titulo = $titulo !== null ? $this->limparTitulo($titulo) : null;

            $ano = null;
            $campoAno = $this->campo($corpo, 'year') ?? $this->campo($corpo, 'date');
            if ($campoAno !== null) {
                $ano = $this->extrairAno($campoAno);
            }

            $doi = null;
            if (preg_match('/\\\\verb\{doi\}\s*\\\\verb\s+(\S+)/', $corpo, $m)) {
                $doi = $this->extrairDoi($m[1]);
            }
            $doi ??= $this->extrairDoi((string) $this->campo($corpo, 'doi'));
            $doi ??= $this->extrairDoi($corpo);

            $autores = [];
            if (preg_match_all('/family=\{?([^,}]+?)\}?,/', $corpo, $a)) {
                $autores = array_map(fn ($n) => NomePessoa::limparLatex($n), $a[1]);
            }
            $veiculo = $this->campo($corpo, 'journaltitle') ?? $this->campo($corpo, 'booktitle') ?? $this->campo($corpo, 'publisher') ?? '';
            $partes = array_filter([
                implode(', ', $autores),
                $ano !== null ? "({$ano})" : null,
                $titulo,
                $this->limparTexto($veiculo),
            ], fn ($p) => $p !== null && $p !== '');

            $refs[] = [
                'chave' => $chave,
                'texto' => implode('. ', $partes),
                'doi' => $doi,
                'ano' => $ano,
                'titulo' => $titulo,
            ];
        }

        return $refs;
    }

    /** Valor de \field{nome}{valor}, com até dois níveis de chaves no valor. */
    private function campo(string $corpo, string $nome): ?string
    {
        if (preg_match('/\\\\field\{'.preg_quote($nome, '/').'\}\{((?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*)\}/', $corpo, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extrairDoi(string $texto): ?string
    {
        $texto = str_replace(['\\_', '\\%', '\\&'], ['_', '%', '&'], $texto);
        if (! preg_match(self::REGEX_DOI, $texto, $m)) {
            return null;
        }

        return rtrim($m[0], '.;)');
    }

    private function semDoi(string $texto): string
    {
        return preg_replace(self::REGEX_DOI, ' ', $texto) ?? $texto;
    }

    private function extrairAno(string $texto): ?int
    {
        return preg_match('/(?<!\d)(?:19|20)\d{2}(?!\d)/', $texto, $m) ? (int) $m[0] : null;
    }

    private function limparTitulo(string $bruto): ?string
    {
        $bruto = preg_replace('/\\\\(?:doi|url)\s*\{[^}]*\}/', '', $bruto) ?? $bruto;
        $titulo = rtrim($this->limparTexto($bruto), " .\t");

        return $titulo === '' ? null : $titulo;
    }

    private function limparTexto(string $bruto): string
    {
        $bruto = preg_replace('/\\\\(?:BBA|BBAA|BBAB|BBC|BBOP|BBCP|BBCQ|BBOQ|BBCQP)\b\{?\}?/', ' ', $bruto) ?? $bruto;
        $bruto = preg_replace('/\\\\newblock\b/', ' ', $bruto) ?? $bruto;
        $bruto = preg_replace('/\\\\(?:doi|url)\s*\{([^}]*)\}/', ' $1 ', $bruto) ?? $bruto;
        $bruto = preg_replace('/\\\\(?:em|Bem|bf|it|sc|sl|rm)\b/', '', $bruto) ?? $bruto;

        return NomePessoa::limparLatex($bruto);
    }
}
