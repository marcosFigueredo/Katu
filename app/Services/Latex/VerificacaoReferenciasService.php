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

    public function __construct(private readonly LeitorBblService $leitor) {}

    public function verificar(TrabalhoSubmissaoLatex $submissao): void
    {
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
                    $titulo = $this->tituloPeloOllama($ref['texto']);
                }
                $achado = $titulo !== null ? $this->porTitulo($titulo) : null;
            }
        } catch (FalhaConsultaReferencia $e) {
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
            $resposta = $this->get('https://api.openalex.org/works', ['search' => $titulo, 'per-page' => 5]);
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
        $resposta = $this->get('https://api.crossref.org/works/'.str_replace('%2F', '/', rawurlencode($doi)));
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
        $obra = $this->get('https://api.openalex.org/works/https://doi.org/'.str_replace('%2F', '/', rawurlencode($doi)));

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

    /** GET JSON. null = 404 (a base não tem); FalhaConsultaReferencia = rede, tempo limite ou erro do serviço. */
    private function get(string $url, array $query = []): ?array
    {
        try {
            $resposta = Http::timeout(15)
                ->withUserAgent('Katu/1.0 (mailto:'.config('mail.from.address').')')
                ->acceptJson()
                ->get($url, $query);
        } catch (ConnectionException $e) {
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

    /** Reserva para referência sem título legível: o Ollama extrai o título. Qualquer falha devolve null. */
    private function tituloPeloOllama(string $texto): ?string
    {
        $texto = mb_substr(trim($texto), 0, 1500);
        if ($texto === '') {
            return null;
        }

        try {
            $resposta = Http::timeout((int) config('ollama.timeout_mapa', 20))
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

            return mb_strlen($titulo) >= self::MIN_TITULO_LEGIVEL ? $titulo : null;
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
