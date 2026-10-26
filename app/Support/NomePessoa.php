<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Limpeza de marcação LaTeX e comparação de nomes/textos sem acento, caixa ou títulos. */
final class NomePessoa
{
    private const TITULOS = ['prof', 'profa', 'professor', 'professora', 'dr', 'dra', 'doutor', 'doutora', 'me', 'msc', 'ma', 'phd', 'esp'];

    /** Comando de acento => [letras base, letras acentuadas]. */
    private const ACENTOS = [
        "'" => ['aeiouyAEIOUY', 'áéíóúýÁÉÍÓÚÝ'],
        '`' => ['aeiouAEIOU', 'àèìòùÀÈÌÒÙ'],
        '^' => ['aeiouAEIOU', 'âêîôûÂÊÎÔÛ'],
        '"' => ['aeiouyAEIOU', 'äëïöüÿÄËÏÖÜ'],
        '~' => ['aonAON', 'ãõñÃÕÑ'],
        'c' => ['cCsS', 'çÇşŞ'],
        'v' => ['cCsSzZ', 'čČšŠžŽ'],
    ];

    /** Converte acentos LaTeX para Unicode e remove comandos, chaves e espaços especiais. */
    public static function limparLatex(string $texto): string
    {
        $texto = preg_replace_callback(
            '/\\\\([\'`^"~cv])\s*(?:\{\s*(\\\\?[A-Za-z])\s*\}|(\\\\?[A-Za-z]))/u',
            function (array $m): string {
                $letra = ltrim($m[2] !== '' ? $m[2] : ($m[3] ?? ''), '\\');
                [$bases, $acentuadas] = self::ACENTOS[$m[1]];
                $pos = mb_strpos($bases, $letra);

                return $pos === false ? $letra : mb_substr($acentuadas, $pos, 1);
            },
            $texto
        ) ?? $texto;

        $texto = preg_replace('/\\\\(?:\\\\|,|;|:|!|\s)|~/', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\\\\([&%$#_])/', '$1', $texto) ?? $texto;
        $texto = preg_replace('/\\\\[A-Za-z]+\*?/', '', $texto) ?? $texto;
        $texto = str_replace(['{', '}'], '', $texto);

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }

    /** Minúsculas, sem acento, pontuação virando espaço, espaços colapsados (serve a títulos e locais). */
    public static function simplificar(string $texto): string
    {
        $texto = Str::lower(Str::ascii(self::limparLatex($texto)));
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    public static function normalizar(string $nome): string
    {
        $nome = self::limparLatex($nome);
        if (str_contains($nome, ',')) {
            [$sobrenome, $resto] = array_map('trim', explode(',', $nome, 2));
            $nome = $resto.' '.$sobrenome;
        }

        $palavras = array_filter(
            explode(' ', self::simplificar($nome)),
            fn (string $p) => $p !== '' && ! in_array($p, self::TITULOS, true)
        );

        return implode(' ', $palavras);
    }

    public static function equivalentes(string $a, string $b): bool
    {
        return self::normalizar($a) === self::normalizar($b);
    }
}
