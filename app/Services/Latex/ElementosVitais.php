<?php

namespace App\Services\Latex;

/** Códigos dos elementos vitais procurados no fonte LaTeX do trabalho. */
final class ElementosVitais
{
    public const TITULO_PT = 'titulo_pt';

    public const TITULO_EN = 'titulo_en';

    public const RESUMO_PT = 'resumo_pt';

    public const PALAVRAS_CHAVE_PT = 'palavras_chave_pt';

    public const ABSTRACT_EN = 'abstract_en';

    public const PALAVRAS_CHAVE_EN = 'palavras_chave_en';

    public const AUTOR = 'autor';

    public const ORIENTADOR = 'orientador';

    public const COORIENTADORES = 'coorientadores';

    public const MEMBROS_BANCA = 'membros_banca';

    public const DATA_DEFESA = 'data_defesa';

    public const LOCAL_DEFESA = 'local_defesa';

    /** Códigos cujo valor é uma lista de nomes. */
    public const LISTAS = [self::COORIENTADORES, self::MEMBROS_BANCA];

    public const PALAVRAS_CHAVE = [self::PALAVRAS_CHAVE_PT, self::PALAVRAS_CHAVE_EN];

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::TITULO_PT, self::TITULO_EN, self::RESUMO_PT, self::PALAVRAS_CHAVE_PT,
            self::ABSTRACT_EN, self::PALAVRAS_CHAVE_EN, self::AUTOR, self::ORIENTADOR,
            self::COORIENTADORES, self::MEMBROS_BANCA, self::DATA_DEFESA, self::LOCAL_DEFESA,
        ];
    }

    /** Descrição curta de cada código (usada no pedido ao Ollama). */
    public static function descricao(string $codigo): string
    {
        return match ($codigo) {
            self::TITULO_PT => 'título do trabalho em português',
            self::TITULO_EN => 'título do trabalho em inglês',
            self::RESUMO_PT => 'resumo em português',
            self::PALAVRAS_CHAVE_PT => 'palavras-chave em português',
            self::ABSTRACT_EN => 'resumo em inglês (abstract)',
            self::PALAVRAS_CHAVE_EN => 'palavras-chave em inglês (keywords)',
            self::AUTOR => 'nome do autor (discente)',
            self::ORIENTADOR => 'nome do orientador',
            self::COORIENTADORES => 'nomes dos coorientadores (lista)',
            self::MEMBROS_BANCA => 'nomes dos membros da banca examinadora (lista)',
            self::DATA_DEFESA => 'data da defesa',
            self::LOCAL_DEFESA => 'local da defesa',
            default => $codigo,
        };
    }
}
