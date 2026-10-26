<?php

namespace App\Services\Latex;

/**
 * Leitura de baixo nível do fonte LaTeX: codificação, comentários, argumentos com
 * chaves balanceadas, corpo de ambientes e de seções. Sem regras de negócio.
 *
 * A instância indexa os comandos e casa as chaves numa única passada linear, de
 * modo que várias consultas sobre o mesmo fonte não reprocessam o texto inteiro
 * (e entradas desbalanceadas não ficam quadráticas).
 */
class FonteLatex
{
    private const DEFINICAO_ANTES = '/\\\\(?:(?:re|provide)?newcommand|DeclareRobustCommand|[gex]?def|let|(?:New|Renew|Provide|Declare)DocumentCommand)\s*\*?\s*\{?\s*$/';

    /** Tamanho máximo de um argumento opcional [..] lido. */
    private const MAX_OPCIONAL = 2000;

    /** @var array<int, int>|null posição de cada { => posição do } correspondente */
    private ?array $fechamentos = null;

    /** @var array<string, list<array{0: string, 1: int}>>|null nome em minúsculas => [nome, posição do nome] */
    private ?array $indice = null;

    /** @var array<int, string|null> posição do nome => argumento lido (cache) */
    private array $argumentos = [];

    public function __construct(public readonly string $texto) {}

    /** Converte para UTF-8 e remove comentários. */
    public static function preparar(string $fonte): self
    {
        return new self(self::removerComentarios(self::paraUtf8($fonte)));
    }

    /** Fonte que não é UTF-8 válido é tratado como Windows-1252 (Latin-1). */
    public static function paraUtf8(string $fonte): string
    {
        return mb_check_encoding($fonte, 'UTF-8') ? $fonte : mb_convert_encoding($fonte, 'UTF-8', 'Windows-1252');
    }

    /** Remove o que vem depois de um % não escapado em cada linha (varredura linear, sem regex). */
    public static function removerComentarios(string $fonte): string
    {
        if (! str_contains($fonte, '%')) {
            return $fonte;
        }
        $saida = '';
        $n = strlen($fonte);
        $i = 0;
        while ($i < $n) {
            $p = strcspn($fonte, '%\\', $i);
            $saida .= substr($fonte, $i, $p);
            $i += $p;
            if ($i >= $n) {
                break;
            }
            if ($fonte[$i] === '\\') {
                $saida .= substr($fonte, $i, 2);
                $i += 2;

                continue;
            }
            // % não escapado: pula até o fim da linha (mantém a quebra)
            $i += strcspn($fonte, "\r\n", $i);
        }

        return $saida;
    }

    /** Colapsa espaços em branco (inclusive quebras de linha) e apara. Nunca devolve '' por falha de regex. */
    public static function normalizar(string $texto): string
    {
        $r = preg_replace('/\s+/u', ' ', $texto) ?? preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($r);
    }

    /**
     * Usos de comandos cujo nome satisfaz $casa, na ordem do fonte, com o primeiro
     * argumento obrigatório. Ignora definições (\newcommand{\X}, \def\X...) e
     * argumentos que contêm parâmetros (#1).
     *
     * @param  callable(string):bool  $casa
     * @return list<string>
     */
    public function argumentosDeComandos(callable $casa): array
    {
        $posicoes = [];
        foreach ($this->indice() as $ocorrencias) {
            if (! $casa($ocorrencias[0][0])) {
                continue;
            }
            foreach ($ocorrencias as [, $pos]) {
                $posicoes[] = $pos;
            }
        }
        sort($posicoes);

        $valores = [];
        foreach ($posicoes as $pos) {
            if (! array_key_exists($pos, $this->argumentos)) {
                $this->argumentos[$pos] = $this->lerArgumento($pos);
            }
            $valor = $this->argumentos[$pos];
            if ($valor !== null) {
                $valores[] = $valor;
            }
        }

        return $valores;
    }

    /**
     * Ocorrências do ambiente $nome: opção entre colchetes logo após o \begin (ou null) e corpo.
     *
     * @return list<array{opcao: ?string, corpo: string}>
     */
    public function ambientes(string $nome): array
    {
        $q = preg_quote($nome, '/');
        if (! preg_match_all('/\\\\(begin|end)\s*\{'.$q.'\}/i', $this->texto, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $ocorrencias = [];
        $nivel = 0;
        $inicio = 0;
        $opcao = null;
        foreach ($m as $t) {
            $fimToken = $t[0][1] + strlen($t[0][0]);
            if (strtolower($t[1][0]) === 'begin') {
                if ($nivel++ === 0) {
                    [$opcao, $inicio] = $this->opcaoAposBegin($fimToken);
                }
            } elseif ($nivel > 0 && --$nivel === 0) {
                $ocorrencias[] = ['opcao' => $opcao, 'corpo' => substr($this->texto, $inicio, $t[0][1] - $inicio)];
            }
        }

        return $ocorrencias;
    }

    /** Corpo de \chapter*{Titulo} (ou \section) até o próximo capítulo/seção/fim do documento. */
    public function secao(string $titulo): ?string
    {
        $q = preg_quote($titulo, '/');
        if (! preg_match('/\\\\(?:chapter|section)\*?\s*(?:\[[^\]]*\])?\s*\{\s*'.$q.'\s*\}/iu', $this->texto, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $inicio = $m[0][1] + strlen($m[0][0]);
        $fim = preg_match('/\\\\(?:chapter|section|part)\b|\\\\end\s*\{document\}|\\\\(?:mainmatter|backmatter|appendix|textual)\b/', $this->texto, $p, PREG_OFFSET_CAPTURE, $inicio)
            ? $p[0][1] : strlen($this->texto);

        return substr($this->texto, $inicio, $fim - $inicio);
    }

    /** @return array<string, list<array{0: string, 1: int}>> */
    private function indice(): array
    {
        if ($this->indice === null) {
            $this->indice = [];
            if (preg_match_all('/\\\\([A-Za-z@]{1,64})/', $this->texto, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as [$nome, $pos]) {
                    $this->indice[strtolower($nome)][] = [$nome, $pos];
                }
            }
        }

        return $this->indice;
    }

    private function lerArgumento(int $posNome): ?string
    {
        $inicio = $posNome - 1;
        if (preg_match(self::DEFINICAO_ANTES, substr($this->texto, max(0, $inicio - 60), min(60, $inicio)))) {
            return null;
        }
        $i = $posNome + strspn($this->texto, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz@', $posNome);
        if (($this->texto[$i] ?? '') === '*') {
            $i++;
        }
        $i = $this->pularOpcionais($i);
        $arg = $this->lerChaves($i);

        return $arg === null || str_contains($arg[0], '#') ? null : $arg[0];
    }

    /** @return array{0: ?string, 1: int} [opção normalizada ou null, início do corpo] */
    private function opcaoAposBegin(int $i): array
    {
        $j = $this->pularEspacos($i);
        if (($this->texto[$j] ?? '') === '[') {
            $grupo = $this->lerColchetes($j);
            if ($grupo !== null) {
                return [self::normalizar($grupo[0]), $grupo[1]];
            }
        }

        return [null, $i];
    }

    private function pularOpcionais(int $i): int
    {
        while (true) {
            $i = $this->pularEspacos($i);
            if (($this->texto[$i] ?? '') !== '[') {
                return $i;
            }
            $grupo = $this->lerColchetes($i);
            if ($grupo === null) {
                return $i;
            }
            $i = $grupo[1];
        }
    }

    private function pularEspacos(int $i): int
    {
        return $i + strspn($this->texto, " \t\r\n\f\v", $i);
    }

    /** @return array{0: string, 1: int}|null [conteúdo, posição após o }] */
    private function lerChaves(int $i): ?array
    {
        if (($this->texto[$i] ?? '') !== '{') {
            return null;
        }
        $fecha = $this->fechamentos()[$i] ?? null;

        return $fecha === null ? null : [substr($this->texto, $i + 1, $fecha - $i - 1), $fecha + 1];
    }

    /** Grupo [..] (limitado a MAX_OPCIONAL bytes), pulando grupos de chaves internos. */
    private function lerColchetes(int $i): ?array
    {
        $limite = min(strlen($this->texto), $i + self::MAX_OPCIONAL);
        $fechaColchete = strpos($this->texto, ']', $i);
        if ($fechaColchete === false || $fechaColchete >= $limite) {
            return null;
        }
        for ($j = $i + 1; $j < $limite; $j++) {
            $j += strcspn($this->texto, '\\{]', $j, $limite - $j);
            if ($j >= $limite) {
                return null;
            }
            $c = $this->texto[$j];
            if ($c === '\\') {
                $j++;
            } elseif ($c === '{') {
                $fecha = $this->fechamentos()[$j] ?? null;
                if ($fecha === null) {
                    return null;
                }
                $j = $fecha;
            } elseif ($c === ']') {
                return [substr($this->texto, $i + 1, $j - $i - 1), $j + 1];
            }
        }

        return null;
    }

    /** Casa todas as chaves do fonte numa passada (escapes \{ \} ignorados). */
    private function fechamentos(): array
    {
        if ($this->fechamentos === null) {
            $this->fechamentos = [];
            $pilha = [];
            preg_match_all('/\\\\.|[{}]/s', $this->texto, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as [$token, $pos]) {
                if ($token === '{') {
                    $pilha[] = $pos;
                } elseif ($token === '}' && $pilha !== []) {
                    $this->fechamentos[array_pop($pilha)] = $pos;
                }
            }
        }

        return $this->fechamentos;
    }
}
