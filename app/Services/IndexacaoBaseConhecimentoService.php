<?php

namespace App\Services;

use App\Models\BaseConhecimentoChunk;
use App\Models\ProgramaDocumentoReferencia;

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

    /**
     * Quebra, embeda e persiste os chunks de um documento de referência,
     * substituindo os chunks anteriores daquele documento. Lança
     * RuntimeException se nenhum trecho puder ser indexado (PDF sem texto
     * extraível, ou 100% das chamadas de embedding falhando).
     *
     * @param array<int, string> $textosPorPagina
     */
    public function indexar(ProgramaDocumentoReferencia $documento, array $textosPorPagina): void
    {
        $chunks = $this->chunkar($textosPorPagina);

        if (empty($chunks)) {
            throw new \RuntimeException('Nenhum texto extraível foi encontrado no PDF.');
        }

        BaseConhecimentoChunk::where('programa_documento_referencia_id', $documento->id)->delete();

        $indexados = 0;

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->embeddings->embedder($chunk['conteudo']);

                BaseConhecimentoChunk::create([
                    'programa_documento_referencia_id' => $documento->id,
                    'programa_id' => $documento->programa_id,
                    'tipo' => $documento->tipo,
                    'pagina' => $chunk['pagina'],
                    'ordem' => $chunk['ordem'],
                    'conteudo' => $chunk['conteudo'],
                    'embedding' => $embedding,
                ]);

                $indexados++;
            } catch (\Throwable $e) {
                continue;
            }
        }

        if ($indexados === 0) {
            throw new \RuntimeException('Nenhum trecho pôde ser indexado (falha ao gerar embeddings).');
        }
    }
}
