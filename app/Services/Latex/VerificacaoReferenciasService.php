<?php

namespace App\Services\Latex;

use App\Models\DocenteProfile;
use App\Models\SubmissaoReferencia;
use App\Models\TrabalhoSubmissaoLatex;
use App\Support\NomePessoa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Confere as referências do .bbl no Crossref/OpenAlex (D8, D9). Referência que nenhuma base
 * encontra fica "não localizada — verificar" (nunca "inexistente"); falha de rede ou de
 * serviço fica "não verificada". O resultado é só do discente e dos avaliadores (regra 8).
 */
class VerificacaoReferenciasService
{
    private const CACHE_SEGUNDOS = 30 * 24 * 3600;

    private const SIMILARIDADE_MINIMA = 0.9;

    private const MIN_TITULO_LEGIVEL = 10;

    /** Bases que já falharam por rede nesta execução: as demais referências nem tentam (disjuntor). */
    private array $basesFora = [];

    private float $inicio = 0.0;

    /** @var callable(): float */
    private $relogio;

    /**
     * @param  int  $orcamentoSegundos  tempo total para consultas externas (bem abaixo do timeout do job);
     *                                  esgotado, o restante fica "não verificada"
     * @param  (callable(): float)|null  $relogio  segundos (injetável nos testes)
     */
    public function __construct(
        private readonly LeitorBblService $leitor,
        private readonly int $orcamentoSegundos = 420,
        ?callable $relogio = null,
    ) {
        $this->relogio = $relogio ?? fn (): float => microtime(true);
    }

    public function verificar(TrabalhoSubmissaoLatex $submissao): void
    {
        $this->basesFora = [];
        $this->inicio = ($this->relogio)();
        $bbl = $submissao->bbl_path && Storage::disk('local')->exists($submissao->bbl_path)
            ? Storage::disk('local')->path($submissao->bbl_path)
            : '';
        $leitura = $this->leitor->ler($bbl);
        $orientador = $this->contextoOrientador($submissao);

        $submissao->referencias()->delete();

        foreach ($leitura as $ref) {
            $submissao->referencias()->create($this->verificarReferencia($ref, $orientador));
        }

        $this->gravarTotais($submissao);
    }

    /**
     * @param  array{chave: ?string, texto: string, doi: ?string, ano: ?int, titulo: ?string}  $ref
     * @param  array{dois: array<string, true>, orcid: ?string, nome: ?string}  $orientador
     */
    private function verificarReferencia(array $ref, array $orientador): array
    {
        $doiTex = $ref['doi'] !== null ? $this->normalizarDoi($ref['doi']) : null;
        $dados = [
            'chave' => $ref['chave'],
            'texto' => $ref['texto'] !== '' ? $ref['texto'] : (string) $ref['chave'],
            'doi_tex' => $ref['doi'],
            'ano_tex' => $ref['ano'],
            'titulo_tex' => $ref['titulo'],
            'status' => 'nao_localizada',
            'fonte' => null,
            'doi_encontrado' => null,
            'ano_encontrado' => null,
            'divergencia_doi' => false,
            'divergencia_ano' => false,
            'do_orientador' => $doiTex !== null && isset($orientador['dois'][$doiTex]),
        ];

        $achado = null;
        try {
            if ($doiTex !== null) {
                $achado = $this->porDoi($doiTex);
            } else {
                $titulo = $ref['titulo'];
                if ($titulo === null || mb_strlen($titulo) < self::MIN_TITULO_LEGIVEL) {
                    $extraido = $this->tituloPeloOllama($ref['texto']);
                    $titulo = $extraido['titulo'] ?? null;
                    if ($ref['ano'] === null && ($extraido['ano'] ?? null) !== null) {
                        $ref['ano'] = $extraido['ano'];
                        $dados['ano_tex'] = $extraido['ano'];
                    }
                }
                $achado = $titulo !== null ? $this->porTitulo($titulo) : null;
            }
        } catch (FalhaConsultaReferencia) {
            $dados['status'] = 'nao_verificada';

            return $dados;
        } catch (Throwable $e) {
            // falha inesperada numa referência não derruba as demais; a mensagem não vai para o discente
            report($e);
            $dados['status'] = 'nao_verificada';

            return $dados;
        }

        if ($achado === null) {
            return $dados;
        }

        $dados['status'] = 'localizada';
        $dados['fonte'] = $achado['fonte'];
        $dados['doi_encontrado'] = $achado['doi'];
        $dados['ano_encontrado'] = $achado['ano'];
        $dados['divergencia_doi'] = $doiTex !== null && $achado['doi'] !== null && $this->normalizarDoi($achado['doi']) !== $doiTex;
        $dados['divergencia_ano'] = $ref['ano'] !== null && $achado['ano'] !== null && $ref['ano'] !== $achado['ano'];
        $dados['do_orientador'] = $dados['do_orientador']
            || ($achado['doi'] !== null && isset($orientador['dois'][$this->normalizarDoi($achado['doi'])]))
            || $this->autorEhOrientador($achado['autores'], $orientador);

        return $dados;
    }

    // ─── consultas ───────────────────────────────────────────────────────

    /** Crossref e, se não achar ou falhar, OpenAlex. null = nenhuma base tem; exceção = não deu para saber. */
    private function porDoi(string $doi): ?array
    {
        $r = Cache::remember('refs-latex:doi:'.sha1($doi), self::CACHE_SEGUNDOS, function () use ($doi) {
            $falhou = false;
            foreach (['crossref', 'openalex'] as $fonte) {
                try {
                    $dados = $fonte === 'crossref' ? $this->crossref($doi) : $this->openalexPorDoi($doi);
                } catch (FalhaConsultaReferencia) {
                    $falhou = true;

                    continue;
                }
                if ($dados !== null) {
                    return ['achado' => true, 'dados' => $dados];
                }
            }
            if ($falhou) {
                throw new FalhaConsultaReferencia('Consulta por DOI incompleta.'); // não vai para o cache
            }

            return ['achado' => false];
        });

        return $r['achado'] ? $r['dados'] : null;
    }

    private function porTitulo(string $titulo): ?array
    {
        $titulo = mb_substr(trim($titulo), 0, 300);
        $r = Cache::remember('refs-latex:titulo:'.sha1(NomePessoa::simplificar($titulo)), self::CACHE_SEGUNDOS, function () use ($titulo) {
            $resposta = $this->get('openalex', 'https://api.openalex.org/works', ['search' => $titulo, 'per-page' => 5]);
            $melhor = null;
            $melhorNota = 0.0;
            foreach ((array) ($resposta['results'] ?? []) as $obra) {
                $nota = $this->similaridade($titulo, (string) ($obra['display_name'] ?? $obra['title'] ?? ''));
                if ($nota > $melhorNota) {
                    $melhorNota = $nota;
                    $melhor = $obra;
                }
            }

            return $melhor !== null && $melhorNota >= self::SIMILARIDADE_MINIMA
                ? ['achado' => true, 'dados' => $this->normalizarOpenAlex($melhor)]
                : ['achado' => false];
        });

        return $r['achado'] ? $r['dados'] : null;
    }

    private function crossref(string $doi): ?array
    {
        $resposta = $this->get('crossref', 'https://api.crossref.org/works/'.str_replace('%2F', '/', rawurlencode($doi)));
        $m = $resposta['message'] ?? null;
        if (! is_array($m)) {
            return null;
        }

        $autores = [];
        foreach ((array) ($m['author'] ?? []) as $a) {
            $autores[] = [
                'nome' => trim(($a['given'] ?? '').' '.($a['family'] ?? '')),
                'orcid' => $a['ORCID'] ?? null,
            ];
        }
        $partes = $m['issued']['date-parts'][0] ?? $m['published-print']['date-parts'][0] ?? $m['published-online']['date-parts'][0] ?? [];

        return [
            'fonte' => 'crossref',
            'doi' => isset($m['DOI']) ? $this->normalizarDoi((string) $m['DOI']) : $doi,
            'ano' => isset($partes[0]) ? (int) $partes[0] : null,
            'titulo' => $m['title'][0] ?? null,
            'autores' => $autores,
        ];
    }

    private function openalexPorDoi(string $doi): ?array
    {
        $obra = $this->get('openalex', 'https://api.openalex.org/works/https://doi.org/'.str_replace('%2F', '/', rawurlencode($doi)));

        return is_array($obra) && $obra !== [] ? $this->normalizarOpenAlex($obra) : null;
    }

    private function normalizarOpenAlex(array $obra): array
    {
        $autores = [];
        foreach ((array) ($obra['authorships'] ?? []) as $a) {
            $autores[] = [
                'nome' => (string) ($a['author']['display_name'] ?? ''),
                'orcid' => $a['author']['orcid'] ?? null,
            ];
        }

        return [
            'fonte' => 'openalex',
            'doi' => ! empty($obra['doi']) ? $this->normalizarDoi((string) $obra['doi']) : null,
            'ano' => isset($obra['publication_year']) ? (int) $obra['publication_year'] : null,
            'titulo' => $obra['display_name'] ?? $obra['title'] ?? null,
            'autores' => $autores,
        ];
    }

    /** false = a base já falhou por rede nesta execução ou o orçamento de tempo acabou. */
    private function podeConsultar(string $base): bool
    {
        return ! isset($this->basesFora[$base])
            && (($this->relogio)() - $this->inicio) < $this->orcamentoSegundos;
    }

    /** GET JSON. null = 404 (a base não tem); FalhaConsultaReferencia = rede, tempo limite, erro do serviço, base fora ou sem orçamento. */
    private function get(string $base, string $url, array $query = []): ?array
    {
        if (! $this->podeConsultar($base)) {
            throw new FalhaConsultaReferencia('Base indisponível ou orçamento de tempo esgotado.');
        }

        try {
            $resposta = Http::connectTimeout(5)->timeout(15)
                ->withUserAgent('Katu/1.0 (mailto:'.config('mail.from.address').')')
                ->acceptJson()
                ->get($url, $query);
        } catch (ConnectionException $e) {
            $this->basesFora[$base] = true;
            throw new FalhaConsultaReferencia($e->getMessage(), 0, $e);
        }

        if ($resposta->status() === 404) {
            return null;
        }
        if (! $resposta->successful()) {
            throw new FalhaConsultaReferencia('HTTP '.$resposta->status());
        }
        $json = $resposta->json();
        if (! is_array($json)) {
            throw new FalhaConsultaReferencia('Resposta inválida.');
        }

        return $json;
    }

    /**
     * Reserva para referência sem título legível: o Ollama extrai título e ano. Resposta ruim devolve null;
     * Ollama fora do ar (rede) ou orçamento esgotado lança FalhaConsultaReferencia (não verificada).
     *
     * @return array{titulo: string, ano: ?int}|null
     */
    private function tituloPeloOllama(string $texto): ?array
    {
        $texto = mb_substr(trim($texto), 0, 1500);
        if ($texto === '') {
            return null;
        }
        if (! $this->podeConsultar('ollama')) {
            throw new FalhaConsultaReferencia('Ollama indisponível ou orçamento de tempo esgotado.');
        }

        try {
            $resposta = Http::connectTimeout(5)->timeout((int) config('ollama.timeout_mapa', 20))
                ->post(rtrim((string) config('ollama.url'), '/').'/api/generate', [
                    'model' => config('ollama.model'),
                    'prompt' => "Extraia o título da obra e o ano desta referência bibliográfica. "
                        ."Responda SOMENTE com JSON no formato {\"titulo\": \"...\", \"ano\": 2020}, "
                        ."usando null no que não constar. Não invente.\n\nReferência:\n{$texto}",
                    'stream' => false,
                    'format' => 'json',
                    'options' => ['temperature' => 0],
                ]);
            if (! $resposta->successful()) {
                return null;
            }
            $dados = json_decode((string) $resposta->json('response'), true);
            $titulo = is_array($dados) && is_string($dados['titulo'] ?? null) ? trim($dados['titulo']) : '';

            if (mb_strlen($titulo) < self::MIN_TITULO_LEGIVEL) {
                return null;
            }
            $ano = is_numeric($dados['ano'] ?? null) ? (int) $dados['ano'] : null;
            $plausivel = $ano !== null && $ano >= 1800 && $ano <= (int) now()->year + 1;

            return ['titulo' => $titulo, 'ano' => $plausivel ? $ano : null];
        } catch (ConnectionException $e) {
            $this->basesFora['ollama'] = true;
            Log::warning('VerificacaoReferencias: Ollama fora do ar: '.$e->getMessage());
            throw new FalhaConsultaReferencia('Ollama fora do ar.', 0, $e);
        } catch (Throwable $e) {
            Log::warning('VerificacaoReferencias: Ollama indisponível: '.$e->getMessage());

            return null;
        }
    }

    // ─── orientador e totais ─────────────────────────────────────────────

    /** @return array{dois: array<string, true>, orcid: ?string, nome: ?string} */
    private function contextoOrientador(TrabalhoSubmissaoLatex $submissao): array
    {
        $vazio = ['dois' => [], 'orcid' => null, 'nome' => null];
        $trabalho = $submissao->trabalhoAcademico;
        $orientacao = $trabalho?->orientacoes()
            ->where('tipo', 'orientador')->where('ativo', true)
            ->with('docenteProgramaVinculo.docenteProfile.user')
            ->orderBy('ordem')->first();
        /** @var DocenteProfile|null $perfil */
        $perfil = $orientacao?->docenteProgramaVinculo?->docenteProfile;
        if ($perfil === null) {
            return $vazio;
        }

        $dois = [];
        foreach ($perfil->orcidWorks()->whereNotNull('doi')->pluck('doi') as $doi) {
            $n = $this->normalizarDoi((string) $doi);
            if ($n !== '') {
                $dois[$n] = true;
            }
        }

        $orcid = $perfil->orcidSnapshot?->orcid_id
            ?? $perfil->orcidWorks()->value('orcid_id')
            ?? $perfil->orcid_url;

        return ['dois' => $dois, 'orcid' => $this->extrairOrcid((string) $orcid), 'nome' => $perfil->user?->name];
    }

    /** @param list<array{nome: string, orcid: ?string}> $autores */
    private function autorEhOrientador(array $autores, array $orientador): bool
    {
        foreach ($autores as $autor) {
            if ($orientador['orcid'] !== null && $this->extrairOrcid((string) $autor['orcid']) === $orientador['orcid']) {
                return true;
            }
            if (($autor['orcid'] ?? null) === null && $orientador['nome'] && $autor['nome'] !== ''
                && NomePessoa::equivalentes($autor['nome'], $orientador['nome'])) {
                return true;
            }
        }

        return false;
    }

    private function gravarTotais(TrabalhoSubmissaoLatex $submissao): void
    {
        $anoEnvio = (int) ($submissao->created_at?->year ?? now()->year);
        $refs = $submissao->referencias()->get();

        $anos = $refs->map(fn (SubmissaoReferencia $r) => $r->anoConsiderado())->filter()->values();
        $recentes = $anos->filter(fn (int $ano) => $ano >= $anoEnvio - 1)->count();
        $pct = $anos->isEmpty() ? null : round($recentes / $anos->count() * 100, 2);

        $submissao->update([
            'refs_status' => 'concluida',
            'refs_total' => $refs->count(),
            'refs_com_ano' => $anos->count(),
            'refs_recentes_pct' => $pct,
            'refs_atualizadas' => $pct === null ? null : $pct > 70,
            'refs_orientador' => $refs->where('do_orientador', true)->count(),
        ]);
    }

    // ─── utilitários ─────────────────────────────────────────────────────

    private function normalizarDoi(string $doi): string
    {
        $doi = strtolower(trim($doi));
        $doi = preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)#', '', $doi) ?? $doi;

        return rtrim($doi, '.');
    }

    private function extrairOrcid(string $texto): ?string
    {
        return preg_match('/\d{4}-\d{4}-\d{4}-\d{3}[\dXx]/', $texto, $m) ? strtoupper($m[0]) : null;
    }

    private function similaridade(string $a, string $b): float
    {
        $a = NomePessoa::simplificar($a);
        $b = NomePessoa::simplificar($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $percentual);

        return $percentual / 100;
    }
}
