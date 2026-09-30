<?php

namespace App\Services;

use App\Models\BaseConhecimentoChunk;
use App\Models\DocumentoAnalise;
use App\Models\DocumentoConformidadeRag;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ConformidadeNormasService
{
    private const VERIFICACOES = [
        'aderencia' => [
            'titulo' => 'Seção "Aderência"',
            'pergunta' => 'Qual o conteúdo obrigatório da seção Aderência ao Programa na dissertação?',
            'termos_busca' => ['aderência ao programa', 'aderência'],
            'buscar_do_fim' => false,
        ],
        'ods' => [
            'titulo' => 'ODS do Trabalho',
            'pergunta' => 'Como o trabalho deve declarar os Objetivos de Desenvolvimento Sustentável (ODS) relacionados à pesquisa?',
            'termos_busca' => ['ods do trabalho', 'objetivos de desenvolvimento sustentável'],
            'buscar_do_fim' => false,
        ],
        'citacao_apa' => [
            'titulo' => 'Padrão de citação (APA)',
            'pergunta' => 'Qual o padrão normativo adotado para citações e organização da lista de referências bibliográficas?',
            'termos_busca' => ['referências bibliográficas', 'referências'],
            'buscar_do_fim' => true,
        ],
        'uso_ia' => [
            'titulo' => 'Uso de Inteligência Artificial',
            'pergunta' => 'Quais são as boas práticas, limites e exigências de declaração do uso de Inteligência Artificial no trabalho?',
            'termos_busca' => ['boas práticas, limites e uso responsável de inteligência artificial', 'uso de inteligência artificial'],
            'buscar_do_fim' => false,
        ],
    ];

    private const VEREDITOS_VALIDOS = ['conforme', 'parcial', 'nao_conforme'];

    public function __construct(private readonly EmbeddingOllamaService $embeddings)
    {
    }

    /**
     * Marca as 4 verificações como falhou com a mesma mensagem — usado quando
     * o job não consegue nem começar a análise (ex.: PDF ilegível, timeout).
     */
    public function marcarTodasComoFalhou(DocumentoAnalise $analise, string $mensagem): void
    {
        foreach (array_keys(self::VERIFICACOES) as $chave) {
            DocumentoConformidadeRag::updateOrCreate(
                ['documento_analise_id' => $analise->id, 'verificacao' => $chave],
                [
                    'status' => 'falhou',
                    'veredito' => null,
                    'justificativa' => null,
                    'trecho_norma_citado' => null,
                    'erro' => mb_substr($mensagem, 0, 500),
                ],
            );
        }
    }

    /**
     * Cria (sem sobrescrever) as 4 verificações como "processando", para que a
     * tela do discente já mostre o card assim que o envio é aceito — antes
     * mesmo do job de fila ser processado.
     */
    public function iniciar(DocumentoAnalise $analise): void
    {
        foreach (array_keys(self::VERIFICACOES) as $chave) {
            DocumentoConformidadeRag::firstOrCreate(
                ['documento_analise_id' => $analise->id, 'verificacao' => $chave],
                ['status' => 'processando'],
            );
        }
    }

    /**
     * Roda as 4 verificações fixas para o manuscrito. Nunca lança exceção —
     * cada verificação grava seu próprio status em documento_conformidade_rag.
     *
     * @param array<int, string> $textosPorPagina
     */
    public function analisar(DocumentoAnalise $analise, int $programaId, array $textosPorPagina): void
    {
        foreach (self::VERIFICACOES as $chave => $definicao) {
            $registro = DocumentoConformidadeRag::firstOrCreate(
                ['documento_analise_id' => $analise->id, 'verificacao' => $chave],
                ['status' => 'processando'],
            );

            try {
                $trechosNorma = $this->buscarTrechosNorma($programaId, $definicao['pergunta']);

                if ($trechosNorma === '') {
                    $registro->update([
                        'status' => 'falhou',
                        'veredito' => null,
                        'justificativa' => null,
                        'trecho_norma_citado' => null,
                        'erro' => 'Nenhum trecho relevante encontrado na base de conhecimento do programa.',
                    ]);
                    continue;
                }

                $trechoManuscrito = $this->extrairTrecho($textosPorPagina, $definicao['termos_busca'], buscarDoFim: $definicao['buscar_do_fim']);
                $prompt = $this->construirPrompt($definicao['titulo'], $trechosNorma, $trechoManuscrito);
                $resposta = $this->chamarOllama($prompt);
                $json = $this->extrairJson($resposta);

                $veredito = $this->normalizarVeredito($json['veredito'] ?? null);

                if ($veredito === null) {
                    $registro->update([
                        'status' => 'falhou',
                        'veredito' => null,
                        'justificativa' => null,
                        'trecho_norma_citado' => null,
                        'erro' => 'Ollama retornou um veredito inválido: ' . json_encode($json['veredito'] ?? null),
                    ]);
                    continue;
                }

                $registro->update([
                    'status' => 'concluida',
                    'veredito' => $veredito,
                    'justificativa' => $json['justificativa'] ?? null,
                    'trecho_norma_citado' => $json['trecho_norma_citado'] ?? null,
                    'erro' => null,
                ]);
            } catch (Throwable $e) {
                $registro->update([
                    'status' => 'falhou',
                    'erro' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }
    }

    private function normalizarVeredito(mixed $veredito): ?string
    {
        if (! is_string($veredito)) {
            return null;
        }

        $normalizado = str_replace(' ', '_', $this->normalizar($veredito));

        return in_array($normalizado, self::VEREDITOS_VALIDOS, true) ? $normalizado : null;
    }

    private function buscarTrechosNorma(int $programaId, string $pergunta, int $topK = 5): string
    {
        $vetorPergunta = $this->embeddings->embedder($pergunta);

        $chunks = BaseConhecimentoChunk::where('programa_id', $programaId)
            ->whereHas('documentoReferencia', fn ($query) => $query->where('status_indexacao', 'concluido'))
            ->get();

        if ($chunks->isEmpty()) {
            return '';
        }

        return $chunks
            ->map(fn (BaseConhecimentoChunk $chunk) => [
                'chunk' => $chunk,
                'similaridade' => $this->embeddings->similaridadeCosseno($vetorPergunta, $chunk->embedding ?? []),
            ])
            ->sortByDesc('similaridade')
            ->take($topK)
            ->map(fn (array $item) => "[p. {$item['chunk']->pagina}] {$item['chunk']->conteudo}")
            ->implode("\n\n");
    }

    private function extrairTrecho(array $textosPorPagina, array $termos, int $maxChars = 3000, bool $buscarDoFim = false): string
    {
        $paginas = $buscarDoFim ? array_reverse($textosPorPagina, true) : $textosPorPagina;

        foreach ($paginas as $numeroPagina => $textoPagina) {
            if ($this->paginaEhSumario((string) $textoPagina)) {
                continue;
            }

            $linhas = preg_split('/\R+/', (string) $textoPagina) ?: [];

            foreach ($linhas as $indice => $linha) {
                if (trim($linha) === '' || $this->linhaPareceEntradaDeSumario($linha)) {
                    continue;
                }

                $linhaNorm = $this->normalizar(trim($linha));
                $bateu = false;

                foreach ($termos as $termo) {
                    if (str_contains($linhaNorm, $this->normalizar($termo))) {
                        $bateu = true;
                        break;
                    }
                }

                if (! $bateu) {
                    continue;
                }

                $conteudo = implode("\n", array_slice($linhas, $indice));

                if (mb_strlen($conteudo) < 300 && isset($textosPorPagina[$numeroPagina + 1])) {
                    $conteudo .= "\n" . $textosPorPagina[$numeroPagina + 1];
                }

                $conteudo = trim($conteudo);

                if (mb_strlen($conteudo) > 30) {
                    return mb_substr($conteudo, 0, $maxChars);
                }
            }
        }

        return '(Trecho não localizado no manuscrito.)';
    }

    private function paginaEhSumario(string $textoPagina): bool
    {
        return str_contains($this->normalizar($textoPagina), 'sumario');
    }

    /**
     * Detecta linhas de sumário/índice: título seguido de líderes de pontos e
     * um número de página no final (ex.: "1.8 Aderência ao Programa . . . . 5").
     */
    private function linhaPareceEntradaDeSumario(string $linha): bool
    {
        return (bool) preg_match('/(\.\s*){3,}\d{1,4}\s*$/u', trim($linha));
    }

    private function construirPrompt(string $titulo, string $trechosNorma, string $trechoManuscrito): string
    {
        return <<<PROMPT
Você é um avaliador de conformidade acadêmica. Avalie se o trecho do MANUSCRITO abaixo atende à norma do programa sobre "{$titulo}", usando como referência exclusivamente os TRECHOS DA NORMA citados. Responda APENAS com o JSON no formato especificado, sem nenhum texto antes ou depois.

=== TRECHOS DA NORMA ===
{$trechosNorma}
=== FIM DOS TRECHOS DA NORMA ===

=== TRECHO DO MANUSCRITO ===
{$trechoManuscrito}
=== FIM DO TRECHO DO MANUSCRITO ===

Formato obrigatório da resposta:
{
  "veredito": "conforme|parcial|nao_conforme",
  "justificativa": "explicação breve e objetiva, em português",
  "trecho_norma_citado": "trecho literal da norma que embasa o veredito"
}

Critérios:
- "conforme": o manuscrito atende integralmente ao que a norma exige para este ponto.
- "parcial": o manuscrito atende parcialmente, ou o conteúdo é ambíguo.
- "nao_conforme": o manuscrito não atende, ou o trecho não foi localizado no manuscrito.
PROMPT;
    }

    private function chamarOllama(string $prompt): string
    {
        $response = Http::timeout(config('ollama.timeout'))
            ->post(config('ollama.url') . '/api/generate', [
                'model' => config('ollama.model'),
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ollama HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200)
            );
        }

        $texto = $response->json('response') ?? '';

        if (empty($texto)) {
            throw new RuntimeException('Ollama retornou resposta vazia.');
        }

        return $texto;
    }

    private function extrairJson(string $texto): array
    {
        $decoded = json_decode($texto, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/u', $texto, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Resposta do Ollama não é JSON válido: ' . mb_substr($texto, 0, 200));
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        $acentos = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $simples = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];

        return trim(preg_replace('/\s+/', ' ', str_replace($acentos, $simples, $texto)));
    }
}
