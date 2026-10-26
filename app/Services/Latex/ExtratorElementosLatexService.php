<?php

namespace App\Services\Latex;

use App\Services\Latex\ElementosVitais as E;

/**
 * Localiza os elementos vitais (títulos, resumos, palavras-chave, autor, orientação,
 * banca, data e local) no fonte LaTeX de qualquer template: classe própria, abnTeX2
 * ou article. Trabalha com regras genéricas e, quando houver, com o mapa do template
 * do programa (formato: [codigo => ['tipo' => ..., 'nome' => ..., 'opcao'? => ...]]).
 */
class ExtratorElementosLatexService
{
    public const TIPOS = ['comando', 'ambiente', 'ambiente_opcao', 'secao', 'padrao_comando'];

    private const ROTULO_PALAVRAS_CHAVE = '/(?:Palavras[- ]?chaves?|Key[- ]?words)\s*\}?\s*:\s*\}?/iu';

    /** Regras genéricas, em ordem de prioridade, no mesmo formato do mapa. */
    private const REGRAS = [
        E::TITULO_PT => [['comando', 'TituloBR'], ['comando', 'titulo'], ['comando', 'tituloPT']],
        E::TITULO_EN => [['comando', 'TituloEN'], ['comando', 'title'], ['comando', 'tituloestrangeiro'], ['comando', 'titleEN']],
        E::RESUMO_PT => [['ambiente', 'resumo'], ['secao', 'Resumo']],
        E::PALAVRAS_CHAVE_PT => [['comando', 'palavrasChavePT'], ['comando', 'palavrasChave'], ['ambiente', 'resumo'], ['secao', 'Resumo']],
        E::ABSTRACT_EN => [['ambiente', 'abstract'], ['ambiente', 'newAbstract'], ['ambiente_opcao', 'resumo', 'Abstract'], ['secao', 'Abstract']],
        E::PALAVRAS_CHAVE_EN => [
            ['comando', 'palavrasChaveEN'], ['comando', 'keywordsEN'], ['comando', 'keywords'],
            ['ambiente', 'abstract'], ['ambiente', 'newAbstract'], ['ambiente_opcao', 'resumo', 'Abstract'], ['secao', 'Abstract'],
        ],
        E::AUTOR => [['comando', 'NomeAutor'], ['comando', 'autor'], ['comando', 'author']],
        E::ORIENTADOR => [['comando', 'OrientadorNome'], ['comando', 'orientador'], ['comando', 'advisor'], ['comando', 'supervisor']],
        E::COORIENTADORES => [['padrao_comando', 'Coorientador[A-Z]?Nome'], ['comando', 'coorientador'], ['comando', 'coadvisor']],
        E::MEMBROS_BANCA => [['padrao_comando', 'Membro[A-Z]Nome'], ['padrao_comando', 'avaliador[A-Z]?'], ['padrao_comando', 'examinador[A-Z]?']],
        E::DATA_DEFESA => [['comando', 'DataDefesa'], ['comando', 'dataDefesa'], ['comando', 'data']],
        E::LOCAL_DEFESA => [['comando', 'LocalDefesa'], ['comando', 'localDefesa'], ['comando', 'local']],
    ];

    /**
     * Descobre, só por regras, onde cada elemento está no template.
     *
     * @return array<string, array{tipo: string, nome: string, opcao?: string}>
     */
    public function descobrirMapa(string $fonte): array
    {
        $fonte = FonteLatex::removerComentarios($fonte);
        $mapa = [];
        foreach (E::todos() as $codigo) {
            foreach ($this->regras($codigo) as $entrada) {
                if ($this->valorDe($fonte, $codigo, $entrada) !== null) {
                    $mapa[$codigo] = $entrada;
                    break;
                }
            }
        }

        return $mapa;
    }

    /**
     * Extrai os elementos vitais. Com $mapa, ele é tentado primeiro; as regras
     * genéricas cobrem os códigos que o mapa não resolver.
     *
     * @return array<string, string|list<string>|null>
     */
    public function extrair(string $fonte, ?array $mapa = null): array
    {
        $fonte = FonteLatex::removerComentarios($fonte);
        $resultado = [];
        foreach (E::todos() as $codigo) {
            $candidatas = $this->regras($codigo);
            $doMapa = $mapa[$codigo] ?? null;
            if (is_array($doMapa) && self::entradaValida($doMapa)) {
                array_unshift($candidatas, $doMapa);
            }

            $resultado[$codigo] = null;
            foreach ($candidatas as $entrada) {
                $valor = $this->valorDe($fonte, $codigo, $entrada);
                if ($valor !== null) {
                    $resultado[$codigo] = $valor;
                    break;
                }
            }
        }

        return $resultado;
    }

    /** Motor de compilação: comentário mágico, senão xelatex se usar fontspec/unicode-math, senão pdflatex. */
    public function detectarMotor(string $fonte): string
    {
        if (preg_match('/^[ \t]*%[ \t]*!\s*TeX\s+(?:TS-)?program\s*=\s*(pdflatex|xelatex|lualatex)\b/im', $fonte, $m)) {
            return strtolower($m[1]);
        }

        $semComentarios = FonteLatex::removerComentarios($fonte);
        if (preg_match('/\\\\(?:usepackage|RequirePackage)\s*(?:\[[^\]]*\])?\s*\{[^}]*\b(?:fontspec|unicode-math)\b[^}]*\}/', $semComentarios)) {
            return 'xelatex';
        }

        return 'pdflatex';
    }

    /** Valida uma entrada de mapa (vinda das regras, do banco ou do Ollama). */
    public static function entradaValida(mixed $entrada): bool
    {
        if (! is_array($entrada) || ! in_array($entrada['tipo'] ?? null, self::TIPOS, true)) {
            return false;
        }
        $nome = $entrada['nome'] ?? null;
        if (! is_string($nome) || $nome === '' || strlen($nome) > 80) {
            return false;
        }

        return match ($entrada['tipo']) {
            'comando' => (bool) preg_match('/^[A-Za-z@]+$/', $nome),
            'ambiente' => (bool) preg_match('/^[A-Za-z@]+\*?$/', $nome),
            'ambiente_opcao' => (bool) preg_match('/^[A-Za-z@]+\*?$/', $nome)
                && is_string($entrada['opcao'] ?? null) && $entrada['opcao'] !== '' && strlen($entrada['opcao']) <= 80,
            'secao' => ! preg_match('/[\\\\{}\x00-\x1F]/', $nome),
            'padrao_comando' => (bool) preg_match('/^[A-Za-z0-9@\[\]\-?+*{},|()]+$/', $nome)
                && self::padraoCompila($nome),
        };
    }

    private static function padraoCompila(string $padrao): bool
    {
        set_error_handler(fn () => true);
        try {
            return preg_match(self::regexPadrao($padrao), '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    /** @return list<array{tipo: string, nome: string, opcao?: string}> */
    private function regras(string $codigo): array
    {
        return array_map(
            fn (array $r) => isset($r[2]) ? ['tipo' => $r[0], 'nome' => $r[1], 'opcao' => $r[2]] : ['tipo' => $r[0], 'nome' => $r[1]],
            self::REGRAS[$codigo] ?? []
        );
    }

    /** @return string|list<string>|null */
    private function valorDe(string $fonte, string $codigo, array $entrada): string|array|null
    {
        $lista = in_array($codigo, E::LISTAS, true);

        if ($entrada['tipo'] === 'comando' || $entrada['tipo'] === 'padrao_comando') {
            $casa = $entrada['tipo'] === 'comando'
                ? fn (string $n) => strcasecmp($n, $entrada['nome']) === 0
                : fn (string $n) => preg_match(self::regexPadrao($entrada['nome']), $n) === 1;
            $valores = array_values(array_unique(array_filter(
                array_map([FonteLatex::class, 'normalizar'], FonteLatex::argumentosDeComandos($fonte, $casa)),
                fn (string $v) => $v !== ''
            )));
            if ($valores === []) {
                return null;
            }

            return $lista ? $valores : $valores[0];
        }

        if ($lista) {
            return null;
        }

        $bloco = $this->bloco($fonte, $entrada);
        if ($bloco === null) {
            return null;
        }

        $valor = in_array($codigo, E::PALAVRAS_CHAVE, true)
            ? $this->palavrasChaveDoBloco($bloco)
            : $this->textoDoBloco($bloco);

        return $valor === '' ? null : $valor;
    }

    private function bloco(string $fonte, array $entrada): ?string
    {
        if ($entrada['tipo'] === 'secao') {
            return FonteLatex::secao($fonte, $entrada['nome']);
        }

        foreach (FonteLatex::ambientes($fonte, $entrada['nome']) as $ocorrencia) {
            $aceita = $entrada['tipo'] === 'ambiente'
                ? $ocorrencia['opcao'] === null
                : $ocorrencia['opcao'] !== null && mb_strtolower($ocorrencia['opcao']) === mb_strtolower($entrada['opcao']);
            if ($aceita) {
                return $ocorrencia['corpo'];
            }
        }

        return null;
    }

    /** Texto do resumo sem a linha de palavras-chave e sem invólucros de idioma. */
    private function textoDoBloco(string $bloco): string
    {
        if (preg_match(self::ROTULO_PALAVRAS_CHAVE, $bloco, $m, PREG_OFFSET_CAPTURE)) {
            $bloco = substr($bloco, 0, $m[0][1]);
            // remove o que sobra do rótulo: \textbf{, \noindent, \vspace{...}
            $bloco = (string) preg_replace(
                '/(?:\s+|\\\\(?:textbf|textit|emph|textsc|noindent|par|bigskip|medskip|smallskip)\b\s*\{?|\\\\vspace\*?\s*\{[^{}]*\})+$/u',
                '',
                $bloco
            );
        }
        $bloco = (string) preg_replace('/\\\\(?:begin|end)\s*\{otherlanguage\*?\}(?:\s*\{[^}]*\})?|\\\\selectlanguage\s*\{[^}]*\}/', ' ', $bloco);

        return FonteLatex::normalizar($bloco);
    }

    /** Palavras-chave após o rótulo "Palavras-chave:"/"Keywords:" até linha em branco ou \end. */
    private function palavrasChaveDoBloco(string $bloco): string
    {
        if (! preg_match(self::ROTULO_PALAVRAS_CHAVE, $bloco, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $resto = substr($bloco, $m[0][1] + strlen($m[0][0]));
        $resto = preg_split('/\R[ \t]*\R|\\\\end\s*\{|\\\\par\b/', $resto, 2)[0];

        return FonteLatex::normalizar($resto);
    }

    private static function regexPadrao(string $padrao): string
    {
        return '/^(?:'.$padrao.')$/i';
    }
}
