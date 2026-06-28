<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\BancaIndicador;
use App\Models\BancaMembro;
use App\Models\OrcidSnapshot;
use App\Models\OrcidWork;
use App\Models\TrabalhoAcademico;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class BancaIndicadorService
{
    public function calcular(Banca $banca): BancaIndicador
    {
        $banca->loadMissing([
            'membros.docenteProgramaVinculo.docenteProfile',
            'trabalhoAcademico.linhaPesquisa',
        ]);

        $avaliadores = $banca->membros
            ->where('papel', 'avaliador')
            ->where('ativo', true)
            ->values();

        $trabalho = $banca->trabalhoAcademico;

        $b1 = $this->calcularB1($avaliadores);
        $b2 = $this->calcularB2($avaliadores);
        $b3 = $this->calcularB3($avaliadores);
        $b4 = $this->calcularAdherenciaOllama($avaliadores, $this->descreverTema($trabalho));
        $b5 = $this->calcularB5($avaliadores);
        $b6 = $this->calcularB6($avaliadores, $banca);
        $b7 = $this->calcularB7($avaliadores);
        $b8 = $this->calcularAdherenciaOllama($avaliadores, $trabalho?->linhaPesquisa?->nome ?? '');

        $pontos = array_sum(array_map(fn ($v) => $v ? 1 : 0, [$b1, $b2, $b3, $b4, $b5, $b6, $b7, $b8]));
        $indice  = $pontos / 8;

        return BancaIndicador::updateOrCreate(
            ['banca_id' => $banca->id],
            [
                'b1' => $b1, 'b2' => $b2, 'b3' => $b3, 'b4' => $b4,
                'b5' => $b5, 'b6' => $b6, 'b7' => $b7, 'b8' => $b8,
                'indice_banca' => $indice,
                'calculado_em' => Carbon::now(),
            ]
        );
    }

    // ── Critérios ────────────────────────────────────────────────────────────

    private function calcularB1(Collection $avaliadores): bool
    {
        if ($avaliadores->isEmpty()) {
            return false;
        }

        return $avaliadores->every(function ($membro) {
            $docente = $membro->docenteProgramaVinculo?->docenteProfile;

            if (! $docente || empty($docente->orcid_url)) {
                return false;
            }

            $snapshot = OrcidSnapshot::where('docente_profile_id', $docente->id)->first();

            return $snapshot?->status_consulta === 'sucesso';
        });
    }

    private function calcularB2(Collection $avaliadores): bool
    {
        return $avaliadores->contains(fn ($m) => $this->ehExternoUneb($m));
    }

    private function calcularB3(Collection $avaliadores): bool
    {
        return $avaliadores
            ->map(fn ($m) => strtoupper(trim($m->docenteProgramaVinculo?->docenteProfile?->instituicao_sigla ?? '')))
            ->filter()
            ->unique()
            ->count() > 1;
    }

    private function calcularB5(Collection $avaliadores): bool
    {
        return $avaliadores
            ->map(fn ($m) => strtolower(trim($m->docenteProgramaVinculo?->docenteProfile?->area_formacao ?? '')))
            ->filter()
            ->unique()
            ->count() > 1;
    }

    private function calcularB6(Collection $avaliadores, Banca $banca): bool
    {
        $programaId = $banca->trabalhoAcademico?->programa_id;

        if (! $programaId || $avaliadores->isEmpty()) {
            return true;
        }

        foreach ($avaliadores as $membro) {
            $count = BancaMembro::where('docente_programa_vinculo_id', $membro->docente_programa_vinculo_id)
                ->where('papel', 'avaliador')
                ->where('id', '!=', $membro->id)
                ->whereHas('banca', function ($q) use ($banca, $programaId) {
                    $q->where('id', '!=', $banca->id)
                      ->where('created_at', '>=', now()->subYear())
                      ->whereHas('trabalhoAcademico', fn ($q2) => $q2->where('programa_id', $programaId));
                })
                ->count();

            if ($count >= 3) {
                return false;
            }
        }

        return true;
    }

    private function calcularB7(Collection $avaliadores): bool
    {
        $temInterno = $avaliadores->contains(
            fn ($m) => $m->docenteProgramaVinculo?->tipo_vinculo === 'interno'
        );

        $temExterno = $avaliadores->contains(fn ($m) => $this->ehExternoUneb($m));

        return $temInterno && $temExterno;
    }

    // ── Ollama (B4 e B8) ─────────────────────────────────────────────────────

    private function calcularAdherenciaOllama(Collection $avaliadores, string $referencia): bool
    {
        if (empty(trim($referencia)) || $avaliadores->isEmpty()) {
            return false;
        }

        $docenteIds = $avaliadores
            ->map(fn ($m) => $m->docenteProgramaVinculo?->docente_profile_id)
            ->filter()
            ->unique();

        $titulos = OrcidWork::whereIn('docente_profile_id', $docenteIds)
            ->whereNotNull('titulo')
            ->where('titulo', '!=', '')
            ->orderByDesc('ano')
            ->limit(20)
            ->pluck('titulo');

        if ($titulos->isEmpty()) {
            return false;
        }

        $listagem = $titulos->map(fn ($t) => "- {$t}")->implode("\n");

        $prompt = <<<PROMPT
Você é um avaliador acadêmico. Analise se as publicações listadas possuem aderência temática ao contexto informado.

CONTEXTO:
{$referencia}

PUBLICAÇÕES DOS AVALIADORES:
{$listagem}

Responda APENAS com JSON: {"aderente": true} ou {"aderente": false}
PROMPT;

        return $this->consultarOllama($prompt);
    }

    private function consultarOllama(string $prompt): bool
    {
        try {
            $response = Http::timeout(30)
                ->post(config('ollama.url') . '/api/generate', [
                    'model'  => config('ollama.model'),
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return false;
            }

            $texto   = $response->json('response') ?? '';
            $decoded = json_decode($texto, true);

            if (! is_array($decoded) && preg_match('/\{[\s\S]*\}/u', $texto, $m)) {
                $decoded = json_decode($m[0], true);
            }

            return (bool) ($decoded['aderente'] ?? false);
        } catch (Throwable $e) {
            return false;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ehExternoUneb(BancaMembro $membro): bool
    {
        $sigla = strtoupper(trim($membro->docenteProgramaVinculo?->docenteProfile?->instituicao_sigla ?? ''));

        return $sigla !== '' && $sigla !== 'UNEB';
    }

    private function descreverTema(?TrabalhoAcademico $trabalho): string
    {
        if (! $trabalho) {
            return '';
        }

        $partes = array_filter([
            $trabalho->titulo,
            $trabalho->palavras_chave,
            $trabalho->resumo ? mb_substr($trabalho->resumo, 0, 500) : null,
        ]);

        return $partes
            ? implode('. ', $partes)
            : ($trabalho->linhaPesquisa?->nome ?? '');
    }
}
