<?php

namespace App\Services;

class IndexacaoBaseConhecimentoService
{
    public function __construct(private readonly EmbeddingOllamaService $embeddings)
    {
    }

    /**
     * Quebra o texto extraído do PDF (por página) em trechos de ~$tamanhoAlvo
     * caracteres, respeitando limites de frase quando possível, mantendo a
     * página de origem para citação.
     *
     * @param array<int, string> $textosPorPagina
     * @return array<int, array{pagina: int, ordem: int, conteudo: string}>
     */
    public function chunkar(array $textosPorPagina, int $tamanhoAlvo = 700): array
    {
        $chunks = [];
        $ordem = 0;

        foreach ($textosPorPagina as $pagina => $texto) {
            $texto = trim(preg_replace('/\s+/', ' ', (string) $texto));

            if ($texto === '') {
                continue;
            }

            $frases = preg_split('/(?<=[.\?\!])\s+/u', $texto) ?: [$texto];
            $buffer = '';

            foreach ($frases as $frase) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($frase) + 1 > $tamanhoAlvo) {
                    $chunks[] = ['pagina' => (int) $pagina, 'ordem' => $ordem++, 'conteudo' => trim($buffer)];
                    $buffer = '';
                }

                $buffer .= ($buffer === '' ? '' : ' ') . $frase;
            }

            if (trim($buffer) !== '') {
                $chunks[] = ['pagina' => (int) $pagina, 'ordem' => $ordem++, 'conteudo' => trim($buffer)];
            }
        }

        return $chunks;
    }
}
