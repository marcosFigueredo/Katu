<?php

namespace App\Services\Latex;

class ProjetoLatex
{
    private const PROFUNDIDADE_MAXIMA = 20;

    public function __construct(
        public readonly string $raiz,
        public readonly string $texPrincipal,
    ) {}

    /** Fonte do .tex principal com \input{} e \include{} substituídos recursivamente. */
    public function fonteExpandido(): string
    {
        $conteudo = (string) @file_get_contents($this->raiz.DIRECTORY_SEPARATOR.$this->texPrincipal);

        return $this->expandir($conteudo, 0);
    }

    public function classeDocumento(): ?string
    {
        $conteudo = (string) @file_get_contents($this->raiz.DIRECTORY_SEPARATOR.$this->texPrincipal);
        foreach (preg_split('/\R/', $conteudo) as $linha) {
            if (preg_match('/^(?:[^%\\\\]|\\\\.)*?\\\\documentclass\s*(?:\[[^\]]*\])?\s*\{([^}]+)\}/', $linha, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    private function expandir(string $texto, int $profundidade): string
    {
        $linhas = preg_split('/(\R)/', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($linhas as $i => $linha) {
            if (! str_contains($linha, '\\input') && ! str_contains($linha, '\\include')) {
                continue;
            }
            $linhas[$i] = preg_replace_callback(
                '/\\\\(?:input|include)\s*\{([^}]*)\}/',
                function (array $m) use ($linha, $profundidade) {
                    // ignora ocorrências após um % não escapado (comentário)
                    $antes = substr($linha, 0, strpos($linha, $m[0]));
                    if (preg_match('/(?<!\\\\)%/', $antes)) {
                        return $m[0];
                    }
                    if ($profundidade >= self::PROFUNDIDADE_MAXIMA) {
                        return '';
                    }
                    $arquivo = $this->resolver(trim($m[1]));

                    return $arquivo === null
                        ? ''
                        : $this->expandir((string) file_get_contents($arquivo), $profundidade + 1);
                },
                $linha
            );
        }

        return implode('', $linhas);
    }

    private function resolver(string $nome): ?string
    {
        $nome = str_replace('\\', '/', $nome);
        if ($nome === '' || str_starts_with($nome, '/') || preg_match('/^[A-Za-z]:/', $nome)) {
            return null;
        }
        if (! str_ends_with(strtolower($nome), '.tex')) {
            $nome .= '.tex';
        }
        $raiz = realpath($this->raiz);
        $real = realpath($raiz.DIRECTORY_SEPARATOR.$nome);
        if ($raiz === false || $real === false || ! is_file($real)) {
            return null;
        }
        if (! str_starts_with($real, $raiz.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }
}
