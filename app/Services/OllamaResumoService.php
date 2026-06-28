<?php

namespace App\Services;

use App\Models\DocumentoAnalise;
use App\Models\DocumentoAnaliseOllama;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaResumoService
{
    /**
     * Extrai o RESUMO do PDF, avalia via Ollama e persiste em documento_analise_ollamas.
     * Nunca lança exceção — falhas são registradas com status='falhou'.
     */
    public function analisar(
        DocumentoAnalise $analise,
        array $textosPorPagina,
        ?string $resumoFormulario = null,
        ?string $palavrasChaveFormulario = null,
    ): DocumentoAnaliseOllama {
        $registro = DocumentoAnaliseOllama::firstOrCreate(
            ['documento_analise_id' => $analise->id],
            ['modelo' => config('ollama.model'), 'status' => 'processando'],
        );

        try {
            // Prioriza o resumo informado pelo discente no formulário
            $textoResumo = $resumoFormulario
                ? trim($resumoFormulario)
                : $this->extrairSecao($textosPorPagina, ['resumo', 'abstract']);

            $palavrasChave = $palavrasChaveFormulario
                ? trim($palavrasChaveFormulario)
                : $this->extrairPalavrasChave($textosPorPagina);

            if (! $textoResumo) {
                $registro->update([
                    'status' => 'falhou',
                    'erro'   => 'Resumo não informado e não localizado no documento.',
                ]);

                return $registro->fresh();
            }

            $prompt   = $this->construirPrompt($textoResumo, $palavrasChave);
            $resposta = $this->chamarOllama($prompt);
            $json     = $this->extrairJson($resposta);

            $registro->update([
                'prompt'    => $prompt,
                'resposta'  => $json,
                'status'    => 'concluida',
                'gerado_em' => Carbon::now(),
            ]);

            return $registro->fresh();
        } catch (Throwable $e) {
            $registro->update([
                'status' => 'falhou',
                'erro'   => mb_substr($e->getMessage(), 0, 500),
            ]);

            return $registro->fresh();
        }
    }

    // ── Extração de seções do PDF ─────────────────────────────────────────────

    private function extrairSecao(array $textosPorPagina, array $termos, int $maxChars = 3000): ?string
    {
        foreach ($textosPorPagina as $numeroPagina => $textoPagina) {
            $linhas = preg_split('/\R+/', $textoPagina) ?: [];

            foreach ($linhas as $indice => $linha) {
                $linhaNorm = $this->normalizar(trim($linha));

                if (! in_array($linhaNorm, $termos, true)) {
                    continue;
                }

                $conteudo = implode("\n", array_slice($linhas, $indice + 1));

                // Inclui página seguinte se o conteúdo for curto
                if (mb_strlen($conteudo) < 300 && isset($textosPorPagina[$numeroPagina + 1])) {
                    $conteudo .= "\n" . $textosPorPagina[$numeroPagina + 1];
                }

                $conteudo = trim($conteudo);

                if (mb_strlen($conteudo) > 50) {
                    return mb_substr($conteudo, 0, $maxChars);
                }
            }
        }

        return null;
    }

    private function extrairPalavrasChave(array $textosPorPagina): ?string
    {
        $rotulos = ['palavras-chave', 'palavras chave', 'keywords', 'key-words', 'key words'];

        foreach ($textosPorPagina as $textoPagina) {
            $paginaNorm = $this->normalizar($textoPagina);

            foreach ($rotulos as $rotulo) {
                $posicao = mb_strpos($paginaNorm, $rotulo);

                if ($posicao === false) {
                    continue;
                }

                $apos     = mb_substr($textoPagina, $posicao + mb_strlen($rotulo));
                $apos     = ltrim($apos, ':.; ');
                $primeira = trim(strtok($apos, "\n") ?: '');

                if (mb_strlen($primeira) > 3) {
                    return $primeira;
                }
            }
        }

        return null;
    }

    // ── Contexto institucional do PPGMSB ─────────────────────────────────────

    private const CONTEXTO_PPGMSB = <<<'CONTEXTO'
O PPGMSB (Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas) é um mestrado acadêmico da Universidade do Estado da Bahia (UNEB), sediado no Campus II – Alagoinhas/BA, vinculado ao Departamento de Ciências Exatas e da Terra. É avaliado pela CAPES na área interdisciplinar de Engenharia, Tecnologia e Gestão.

MISSÃO: Desenvolver estudos interdisciplinares, produzindo conhecimentos científicos qualificados através da formação de acadêmicos e profissionais de excelência.

ÁREA DE CONCENTRAÇÃO: Modelagem Aplicada a Biossistemas — natureza interdisciplinar, integrando matemática, física, engenharia, biologia, ciências ambientais, computação e gestão. Abrange as especialidades: Ciências Agrárias, Ciências Biológicas, Ciências Exatas e da Terra e Engenharias.

Eixos temáticos da área de concentração:
- Reconhecimento de padrões, estatística multivariada, análise de dados e algoritmos computacionais voltados a diagnóstico, saúde e ciências da vida
- Modelagem matemática, computacional e simbólica de sistemas complexos
- Monitoramento de recursos hídricos, fenômenos ambientais e modelagem hidrológica
- Saneamento ambiental (resíduos sólidos, água, esgotamento sanitário e drenagem)
- Sistemas inteligentes: aprendizado de máquina, mineração de dados, agentes computacionais — aplicados a educação, energia, mobilidade e indústria
- Biossistemas: animais, plantas, ecossistemas, microbiota, biotecnologia e bioprospecção
- Engenharia ambiental, impactos ambientais e tecnologias sustentáveis
- Física aplicada à modelagem de fenômenos naturais, sistemas energéticos e processos industriais
- Modelagem urbana, planejamento territorial e gestão pública sustentável
- Química aplicada, monitoramento ambiental e desenvolvimento de tecnologias limpas
- Gestão, inovação organizacional e análise de impactos socioeconômicos e ambientais

LINHAS DE PESQUISA:

Linha 01 – Modelagem e Otimização de Biossistemas:
Foco no desenvolvimento e aplicação de metodologias numéricas e analíticas para modelagem computacional de biossistemas e fenômenos complexos. Usa modelos físicos e matemáticos para descrever, analisar e otimizar sistemas dinâmicos. Contempla otimização, problemas inversos, modelos estocásticos, simulação computacional, algoritmos e análise de dados científicos. Resultados típicos: automação agrícola, simulação de sistemas, saúde e monitoramento de doenças, logística, transporte, planejamento urbano e sistemas energéticos.

Linha 02 – Análise de Biossistemas:
Dedica-se ao estudo, compreensão e melhoria do desempenho de sistemas biológicos e ambientais, com ênfase em sustentabilidade e tecnologias inovadoras de impacto local e regional. Abrange uso da terra, solo, fauna e flora, agricultura, processos microbianos, bio-produção, ecologia e recursos hídricos. Integra também áreas da saúde (Biomecânica, Biofísica, Bioquímica, Imunologia) e abordagem humana/social (qualidade de vida, sistemas socioecológicos, conservação da natureza).
CONTEXTO;

    // ── Prompt ────────────────────────────────────────────────────────────────

    private function construirPrompt(string $textoResumo, ?string $palavrasChave): string
    {
        $secaoKw = $palavrasChave
            ? "PALAVRAS-CHAVE INFORMADAS:\n{$palavrasChave}"
            : "PALAVRAS-CHAVE INFORMADAS:\nnão identificadas no documento";

        $contexto = self::CONTEXTO_PPGMSB;

        return <<<PROMPT
Você é um avaliador acadêmico do PPGMSB/UNEB. Use o contexto do programa abaixo para avaliar a aderência do resumo ao programa.

=== CONTEXTO DO PPGMSB ===
{$contexto}
=== FIM DO CONTEXTO ===

Analise o RESUMO abaixo e responda APENAS com o JSON no formato especificado. Não inclua nenhum texto antes ou depois do JSON.

RESUMO:
{$textoResumo}

{$secaoKw}

Formato obrigatório da resposta:
{
  "clareza": "adequada|parcial|insuficiente",
  "objetivo_identificado": true,
  "metodo_identificado": true,
  "resultados_ou_resultados_esperados_identificados": true,
  "conclusao_ou_contribuicao_identificada": true,
  "palavras_chave_identificadas": true,
  "palavras_chave_quantidade": 0,
  "palavras_chave_adequadas_ao_resumo": true,
  "palavras_chave_observacao": "",
  "aderencia_ao_programa": "aparente|parcial|não aparente",
  "observacoes": []
}

Critérios de avaliação:
- clareza: "adequada" = texto claro e coeso; "parcial" = problemas menores de redação; "insuficiente" = confuso ou incompleto
- objetivo_identificado: true se o problema ou objetivo de pesquisa está claramente enunciado
- metodo_identificado: true se a metodologia ou abordagem está descrita
- resultados_ou_resultados_esperados_identificados: true se há resultados obtidos ou esperados mencionados
- conclusao_ou_contribuicao_identificada: true se há conclusão ou contribuição científica identificável
- palavras_chave_identificadas: true se as palavras-chave refletem os temas do resumo
- palavras_chave_quantidade: número inteiro de palavras-chave informadas
- palavras_chave_adequadas_ao_resumo: true se as palavras-chave são pertinentes ao conteúdo
- palavras_chave_observacao: observação breve sobre palavras-chave (string vazia se adequadas)
- aderencia_ao_programa: "aparente" = tema claramente de modelagem/simulação de biossistemas; "parcial" = alguma relação; "não aparente" = sem relação
- observacoes: lista de até 5 strings com sugestões construtivas para o estudante melhorar o resumo
PROMPT;
    }

    // ── Chamada à API do Ollama ───────────────────────────────────────────────

    private function chamarOllama(string $prompt): string
    {
        $response = Http::timeout(config('ollama.timeout'))
            ->post(config('ollama.url') . '/api/generate', [
                'model'  => config('ollama.model'),
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Ollama HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200)
            );
        }

        $texto = $response->json('response') ?? '';

        if (empty($texto)) {
            throw new \RuntimeException('Ollama retornou resposta vazia.');
        }

        return $texto;
    }

    private function extrairJson(string $texto): array
    {
        $decoded = json_decode($texto, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Tenta localizar bloco JSON na resposta (fallback se o modelo adicionou texto extra)
        if (preg_match('/\{[\s\S]*\}/u', $texto, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException(
            'Resposta do Ollama não é JSON válido: ' . mb_substr($texto, 0, 200)
        );
    }

    // ── Normalização ──────────────────────────────────────────────────────────

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        $acentos = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $simples  = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];

        return trim(preg_replace('/\s+/', ' ', str_replace($acentos, $simples, $texto)));
    }
}
