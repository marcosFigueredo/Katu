<?php

namespace App\Services\Latex;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pede ao Ollama, só para os códigos que as regras não resolveram, onde cada
 * elemento vital fica no template LaTeX do programa. Nunca lança exceção:
 * qualquer falha (rede, HTTP, JSON) devolve [].
 */
class MapaTemplateOllamaService
{
    private const MAX_CARACTERES_FONTE = 12000;

    /**
     * @param  list<string>  $codigosFaltantes
     * @return array<string, array{tipo: string, nome: string, opcao?: string}>
     */
    public function sugerir(string $fonte, array $codigosFaltantes): array
    {
        $codigos = array_values(array_intersect($codigosFaltantes, ElementosVitais::todos()));
        if ($codigos === []) {
            return [];
        }

        try {
            $resposta = Http::timeout((int) config('ollama.timeout_mapa', 20))
                ->post(rtrim((string) config('ollama.url'), '/').'/api/generate', [
                    'model' => config('ollama.model'),
                    'prompt' => $this->prompt($fonte, $codigos),
                    'stream' => false,
                    'format' => 'json',
                    'options' => ['temperature' => 0, 'num_ctx' => 8192],
                ]);

            if (! $resposta->successful()) {
                Log::warning('MapaTemplateOllama: HTTP '.$resposta->status());

                return [];
            }

            $dados = json_decode((string) $resposta->json('response'), true);
            if (! is_array($dados)) {
                return [];
            }
            if (isset($dados['mapa']) && is_array($dados['mapa'])) {
                $dados = $dados['mapa'];
            }

            $mapa = [];
            foreach ($codigos as $codigo) {
                $entrada = $dados[$codigo] ?? null;
                if (! ExtratorElementosLatexService::entradaValida($entrada)) {
                    continue;
                }
                $mapa[$codigo] = ['tipo' => $entrada['tipo'], 'nome' => $entrada['nome']];
                if ($entrada['tipo'] === 'ambiente_opcao') {
                    $mapa[$codigo]['opcao'] = $entrada['opcao'];
                }
            }

            return $mapa;
        } catch (Throwable $e) {
            Log::warning('MapaTemplateOllama: falha ao sugerir mapa: '.$e->getMessage());

            return [];
        }
    }

    /** @param list<string> $codigos */
    private function prompt(string $fonte, array $codigos): string
    {
        $lista = implode("\n", array_map(fn ($c) => "- {$c}: ".ElementosVitais::descricao($c), $codigos));

        return <<<PROMPT
Você analisa o template LaTeX de um programa de pós-graduação. Indique ONDE, no fonte,
o aluno informa cada elemento abaixo. Responda SOMENTE com um objeto JSON cujas chaves
são os códigos pedidos e cujos valores têm o formato:
{"tipo": "comando"|"ambiente"|"ambiente_opcao"|"secao"|"padrao_comando", "nome": "...", "opcao": "..."}
- comando: o valor é o 1º argumento de \\nome{...} (informe o nome sem a barra).
- ambiente: o valor é o corpo de \\begin{nome}...\\end{nome}.
- ambiente_opcao: como ambiente, mas só a ocorrência \\begin{nome}[opcao].
- secao: o corpo de \\chapter*{nome} até o próximo capítulo.
- padrao_comando: expressão regular para nomes de vários comandos (ex.: Membro[A-Z]Nome).
Omita os códigos que o template não tiver. Não invente comandos que não aparecem no fonte.

Códigos pedidos:
{$lista}

Fonte do template:
{$this->trecho($fonte)}
PROMPT;
    }

    /** Pré-texto do template (até o início do texto principal), limitado em tamanho. */
    private function trecho(string $fonte): string
    {
        $fonte = FonteLatex::removerComentarios(FonteLatex::paraUtf8($fonte));
        if (preg_match('/\\\\(?:mainmatter|textual)\b/', $fonte, $m, PREG_OFFSET_CAPTURE)) {
            $fonte = substr($fonte, 0, $m[0][1]);
        }
        $fonte = (string) preg_replace('/\n\s*\n+/', "\n", $fonte);

        return mb_substr($fonte, 0, self::MAX_CARACTERES_FONTE);
    }
}
