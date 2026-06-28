<?php

namespace App\Services;

use App\Models\DocenteProfile;
use App\Models\OrcidSnapshot;
use App\Models\OrcidWork;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class OrcidService
{
    private const BASE_URL      = 'https://pub.orcid.org/v3.0';
    private const HEADERS       = ['Accept' => 'application/vnd.orcid+json'];
    private const OPENALEX_URL  = 'https://api.openalex.org/authors/';
    private const USER_AGENT    = 'Katu/1.0 (mailto:katu@ppgmsb.uneb.br)';
    private const TIMEOUT       = 20;

    public function extrairId(string $orcidUrl): string
    {
        return preg_replace('/^https?:\/\/orcid\.org\//', '', trim($orcidUrl));
    }

    public function sincronizar(DocenteProfile $docente): OrcidSnapshot
    {
        $orcidId  = $this->extrairId($docente->orcid_url);
        $snapshot = OrcidSnapshot::firstOrNew(['docente_profile_id' => $docente->id]);

        $snapshot->fill([
            'orcid_id'        => $orcidId,
            'url_orcid'       => $docente->orcid_url,
            'status_consulta' => 'pendente',
        ]);
        $snapshot->save();

        try {
            $perfil = $this->consultarPerfil($orcidId);
            $obras  = $this->extrairObras($orcidId);

            $nome = data_get($perfil, 'person.name.credit-name.value')
                ?? trim(
                    (data_get($perfil, 'person.name.given-names.value') ?? '')
                    . ' '
                    . (data_get($perfil, 'person.name.family-name.value') ?? '')
                );

            $openAlex = $this->consultarOpenAlex($orcidId);

            $snapshot->update([
                'nome_publico'        => $nome ?: null,
                'dados_json'          => $perfil,
                'total_obras'         => count($obras),
                'total_obras_com_doi' => collect($obras)->filter(fn ($o) => ! empty($o['doi']))->count(),
                'indice_h'            => $openAlex['h_index'],
                'total_citacoes'      => $openAlex['cited_by_count'],
                'i10_index'           => $openAlex['i10_index'],
                'works_count_openalex'=> $openAlex['works_count'],
                'status_consulta'     => 'sucesso',
                'mensagem_erro'       => null,
                'consultado_em'       => Carbon::now(),
            ]);

            OrcidWork::where('docente_profile_id', $docente->id)->delete();

            $agora = Carbon::now();

            foreach ($obras as $obra) {
                OrcidWork::create([
                    'docente_profile_id' => $docente->id,
                    'orcid_id'           => $orcidId,
                    'titulo'             => $obra['titulo'],
                    'ano'                => $obra['ano'],
                    'tipo'               => $obra['tipo'],
                    'doi'                => $obra['doi'],
                    'journal'            => $obra['journal'],
                    'url'                => $obra['url'],
                    'fonte'              => 'orcid',
                    'consultado_em'      => $agora,
                ]);
            }
        } catch (Throwable $e) {
            $snapshot->update([
                'status_consulta' => 'erro',
                'mensagem_erro'   => mb_substr($e->getMessage(), 0, 500),
                'consultado_em'   => Carbon::now(),
            ]);
        }

        return $snapshot->fresh();
    }

    // ── API calls ────────────────────────────────────────────────────────────

    private function consultarOpenAlex(string $orcidId): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::OPENALEX_URL . 'https://orcid.org/' . $orcidId);

            if (! $response->successful()) {
                return ['h_index' => null, 'cited_by_count' => null, 'i10_index' => null, 'works_count' => null];
            }

            $data = $response->json();

            return [
                'h_index'       => data_get($data, 'summary_stats.h_index'),
                'cited_by_count'=> data_get($data, 'cited_by_count'),
                'i10_index'     => data_get($data, 'summary_stats.i10_index'),
                'works_count'   => data_get($data, 'works_count'),
            ];
        } catch (Throwable) {
            return ['h_index' => null, 'cited_by_count' => null, 'i10_index' => null, 'works_count' => null];
        }
    }


    private function consultarPerfil(string $orcidId): array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders(self::HEADERS)
            ->get(self::BASE_URL . "/{$orcidId}/record");

        if (! $response->successful()) {
            throw new \RuntimeException("ORCID API {$response->status()} para {$orcidId}");
        }

        return $response->json() ?? [];
    }

    private function extrairObras(string $orcidId): array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders(self::HEADERS)
            ->get(self::BASE_URL . "/{$orcidId}/works");

        if (! $response->successful()) {
            throw new \RuntimeException("ORCID works {$response->status()} para {$orcidId}");
        }

        $data  = $response->json() ?? [];
        $obras = [];

        foreach ($data['group'] ?? [] as $group) {
            $summary = $group['work-summary'][0] ?? null;

            if (! $summary) {
                continue;
            }

            $doi = null;
            foreach ($summary['external-ids']['external-id'] ?? [] as $extId) {
                if ($extId['external-id-type'] === 'doi') {
                    $doi = $extId['external-id-value'];
                    break;
                }
            }

            $titulo = data_get($summary, 'title.title.value', '');

            if (empty(trim($titulo))) {
                continue;
            }

            $obras[] = [
                'titulo'  => $titulo,
                'ano'     => data_get($summary, 'publication-date.year.value'),
                'tipo'    => $summary['type'] ?? null,
                'doi'     => $doi,
                'journal' => data_get($summary, 'journal-title.value'),
                'url'     => data_get($summary, 'url.value'),
            ];
        }

        return $obras;
    }
}
