<?php

namespace App\Services;

use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoIndicador;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class BibliometriaService
{
    private const CROSSREF_URL = 'https://api.crossref.org/works/';
    private const OPENALEX_URL = 'https://api.openalex.org/works';
    private const TIMEOUT      = 20;
    private const USER_AGENT = 'Katu/1.0 (mailto:katu@ppgmsb.uneb.br)';

    public function sincronizar(TrabalhoAcademico $trabalho): TrabalhoIndicador
    {
        $trabalho->loadMissing([
            'discenteVinculo.discenteProfile',
            'orientacoes.docenteProgramaVinculo.docenteProfile',
        ]);

        $indicador = TrabalhoIndicador::firstOrNew(['trabalho_academico_id' => $trabalho->id]);

        $orcidDiscente = $trabalho->discenteVinculo?->discenteProfile?->orcid_url;

        $orientacaoPrincipal = $trabalho->orientacoes->where('tipo', 'orientador')->where('ativo', true)->first();
        $orcidOrientador = $orientacaoPrincipal?->docenteProgramaVinculo?->docenteProfile?->orcid_url;

        $publicacoes        = [];
        $totalCitacoes      = 0;
        $coautoriaOrientador    = false;
        $coautoriaInternacional = false;
        $presencaBasesIndexadas = false;

        if ($orcidDiscente) {
            $orcidId     = $this->extrairOrcidId($orcidDiscente);
            $orcidOrientadorId = $orcidOrientador ? $this->extrairOrcidId($orcidOrientador) : null;

            $publicacoes = $this->buscarPublicacoesOpenAlex($orcidId);

            foreach ($publicacoes as &$pub) {
                if (!empty($pub['doi'])) {
                    $citacoes = $this->buscarCitacoesCrossref($pub['doi']);
                    $pub['citacoes'] = $citacoes;
                    $totalCitacoes += $citacoes;
                }

                if (!empty($pub['is_oa']) || !empty($pub['host_venue'])) {
                    $presencaBasesIndexadas = true;
                }

                if ($orcidOrientadorId && !empty($pub['authorships'])) {
                    foreach ($pub['authorships'] as $authorship) {
                        $autorOrcid = $authorship['author']['orcid'] ?? '';
                        if (str_contains($autorOrcid, $orcidOrientadorId)) {
                            $coautoriaOrientador = true;
                        }
                        $pais = $authorship['institutions'][0]['country_code'] ?? null;
                        if ($pais && $pais !== 'BR') {
                            $coautoriaInternacional = true;
                        }
                    }
                }
            }
            unset($pub);
        }

        $indicador->fill([
            'total_publicacoes_discente' => \count($publicacoes),
            'total_com_doi'              => collect($publicacoes)->filter(fn ($p) => !empty($p['doi']))->count(),
            'total_citacoes_crossref'    => $totalCitacoes,
            'coautoria_orientador'       => $coautoriaOrientador,
            'coautoria_internacional'    => $coautoriaInternacional,
            'presenca_bases_indexadas'   => $presencaBasesIndexadas,
            'dados_json'                 => \count($publicacoes) > 0 ? $publicacoes : null,
            'consultado_em'              => Carbon::now(),
        ]);

        $indicador->save();

        return $indicador;
    }

    // ── OpenAlex ──────────────────────────────────────────────────────────────

    private function buscarPublicacoesOpenAlex(string $orcidId): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::OPENALEX_URL, [
                    'filter'   => "author.orcid:https://orcid.org/{$orcidId}",
                    'per_page' => 50,
                    'select'   => 'id,doi,title,publication_year,type,authorships,is_oa,host_venue,cited_by_count',
                ]);

            if (!$response->successful()) {
                return [];
            }

            return collect($response->json('results') ?? [])
                ->map(fn ($w) => [
                    'id'         => $w['id'] ?? null,
                    'doi'        => isset($w['doi']) ? str_replace('https://doi.org/', '', $w['doi']) : null,
                    'titulo'     => $w['title'] ?? null,
                    'ano'        => $w['publication_year'] ?? null,
                    'tipo'       => $w['type'] ?? null,
                    'is_oa'      => $w['is_oa'] ?? false,
                    'host_venue' => $w['host_venue']['display_name'] ?? null,
                    'authorships'=> $w['authorships'] ?? [],
                    'citacoes'   => $w['cited_by_count'] ?? 0,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    // ── Crossref ──────────────────────────────────────────────────────────────

    private function buscarCitacoesCrossref(string $doi): int
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::CROSSREF_URL . urlencode($doi));

            if (!$response->successful()) {
                return 0;
            }

            return (int) ($response->json('message.is-referenced-by-count') ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function extrairOrcidId(string $orcidUrl): string
    {
        return preg_replace('/^https?:\/\/orcid\.org\//', '', trim($orcidUrl));
    }
}
