<?php

namespace App\Services\Latex;

/**
 * Leitura de baixo nível do fonte LaTeX: comentários, argumentos com chaves
 * balanceadas, corpo de ambientes e de seções. Sem regras de negócio.
 */
class FonteLatex
{
    private const DEFINICAO_ANTES = '/\\\\(?:(?:re|provide)?newcommand|DeclareRobustCommand|[gex]?def|let|(?:New|Renew|Provide|Declare)DocumentCommand)\s*\*?\s*\{?\s*$/';

    /** Remove o que vem depois de um % não escapado em cada linha. */
    public static function removerComentarios(string $fonte): string
    {
        return (string) preg_replace('/^((?:[^%\\\\\r\n]|\\\\.)*)%[^\r\n]*/m', '$1', $fonte);
    }

    /** Colapsa espaços em branco (inclusive quebras de linha) e apara. */
    public static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Usos de comandos cujo nome satisfaz $casa, na ordem do fonte, com o primeiro
     * argumento obrigatório. Ignora definições (\newcommand{\X}, \def\X...) e
     * argumentos que contêm parâmetros (#1).
     *
     * @param  callable(string):bool  $casa
     * @return list<string>
     */
    public static function argumentosDeComandos(string $fonte, callable $casa): array
    {
        if (! preg_match_all('/\\\\([A-Za-z@]{1,64})/', $fonte, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $valores = [];
        foreach ($m[1] as [$nome, $pos]) {
            if (! $casa($nome)) {
                continue;
            }
            $inicio = $pos - 1;
            if (preg_match(self::DEFINICAO_ANTES, substr($fonte, max(0, $inicio - 60), min(60, $inicio)))) {
                continue;
            }
            $i = $pos + strlen($nome);
            if (($fonte[$i] ?? '') === '*') {
                $i++;
            }
            $i = self::pularOpcionais($fonte, $i);
            $arg = self::lerGrupo($fonte, $i, '{', '}');
            if ($arg === null || str_contains($arg[0], '#')) {
                continue;
            }
            $valores[] = $arg[0];
        }

        return $valores;
    }

    /**
     * Ocorrências do ambiente $nome: opção entre colchetes logo após o \begin (ou null) e corpo.
     *
     * @return list<array{opcao: ?string, corpo: string}>
     */
    public static function ambientes(string $fonte, string $nome): array
    {
        $q = preg_quote($nome, '/');
        $abre = '/\\\\begin\s*\{'.$q.'\}/i';
        $tokens = '/\\\\(begin|end)\s*\{'.$q.'\}/i';
        $ocorrencias = [];
        $desloc = 0;

        while (preg_match($abre, $fonte, $m, PREG_OFFSET_CAPTURE, $desloc)) {
            $i = $m[0][1] + strlen($m[0][0]);
            $opcao = null;
            $j = self::pularEspacos($fonte, $i);
            if (($fonte[$j] ?? '') === '[') {
                $grupo = self::lerGrupo($fonte, $j, '[', ']');
                if ($grupo !== null) {
                    $opcao = self::normalizar($grupo[0]);
                    $i = $grupo[1];
                }
            }

            $nivel = 1;
            $cursor = $i;
            $fim = null;
            while (preg_match($tokens, $fonte, $t, PREG_OFFSET_CAPTURE, $cursor)) {
                $nivel += strtolower($t[1][0]) === 'begin' ? 1 : -1;
                $cursor = $t[0][1] + strlen($t[0][0]);
                if ($nivel === 0) {
                    $fim = $t[0][1];
                    break;
                }
            }
            if ($fim === null) {
                break;
            }
            $ocorrencias[] = ['opcao' => $opcao, 'corpo' => substr($fonte, $i, $fim - $i)];
            $desloc = $cursor;
        }

        return $ocorrencias;
    }

    /** Corpo de \chapter*{Titulo} (ou \section) até o próximo capítulo/seção/fim do documento. */
    public static function secao(string $fonte, string $titulo): ?string
    {
        $q = preg_quote($titulo, '/');
        if (! preg_match('/\\\\(?:chapter|section)\*?\s*(?:\[[^\]]*\])?\s*\{\s*'.$q.'\s*\}/iu', $fonte, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $inicio = $m[0][1] + strlen($m[0][0]);
        $fim = preg_match('/\\\\(?:chapter|section|part)\b|\\\\end\s*\{document\}|\\\\(?:mainmatter|backmatter|appendix|textual)\b/', $fonte, $p, PREG_OFFSET_CAPTURE, $inicio)
            ? $p[0][1] : strlen($fonte);

        return substr($fonte, $inicio, $fim - $inicio);
    }

    /** Pula espaços e grupos opcionais [..] a partir de $i. */
    private static function pularOpcionais(string $fonte, int $i): int
    {
        while (true) {
            $i = self::pularEspacos($fonte, $i);
            if (($fonte[$i] ?? '') !== '[') {
                return $i;
            }
            $grupo = self::lerGrupo($fonte, $i, '[', ']');
            if ($grupo === null) {
                return $i;
            }
            $i = $grupo[1];
        }
    }

    private static function pularEspacos(string $fonte, int $i): int
    {
        $n = strlen($fonte);
        while ($i < $n && ctype_space($fonte[$i])) {
            $i++;
        }

        return $i;
    }

    /**
     * Lê um grupo balanceado que começa em $i com $abre (ignorando escapes como \{).
     * Colchetes respeitam chaves internas. Devolve [conteúdo, posição após o fechamento].
     *
     * @return array{0: string, 1: int}|null
     */
    private static function lerGrupo(string $fonte, int $i, string $abre, string $fecha): ?array
    {
        if (($fonte[$i] ?? '') !== $abre) {
            return null;
        }
        $n = strlen($fonte);
        $nivel = 0;
        $chaves = 0;
        for ($j = $i; $j < $n; $j++) {
            $c = $fonte[$j];
            if ($c === '\\') {
                $j++;

                continue;
            }
            if ($abre === '[' && $c === '{') {
                $chaves++;
            } elseif ($abre === '[' && $c === '}') {
                $chaves--;
            } elseif ($c === $abre && $chaves === 0) {
                $nivel++;
            } elseif ($c === $fecha && $chaves === 0) {
                $nivel--;
                if ($nivel === 0) {
                    return [substr($fonte, $i + 1, $j - $i - 1), $j + 1];
                }
            }
        }

        return null;
    }
}
