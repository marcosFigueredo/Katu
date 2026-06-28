<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\Barema;
use App\Models\MabacAnalise;
use App\Models\MabacResultado;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MabacService
{
    /**
     * Cria e persiste uma análise MABAC completa.
     */
    public function analisar(MabacAnalise $analise): MabacAnalise
    {
        $analise->loadMissing('barema.itens');

        $bancas = $this->coletarBancas($analise);

        if ($bancas->isEmpty()) {
            return $analise;
        }

        $itens    = $analise->barema->itens->filter(fn ($i) => $i->ativo)->values();
        $pesos    = $itens->pluck('peso', 'id')->map(fn ($p) => (float) $p);
        $somaP    = $pesos->sum();
        $pesosNorm = $somaP > 0 ? $pesos->map(fn ($p) => $p / $somaP) : $pesos->map(fn () => 1 / $itens->count());

        // Matriz de médias por banca e por item: [banca_id][item_id] = media_nota
        $matriz = $this->montarMatriz($bancas, $itens);

        if (empty($matriz)) {
            return $analise;
        }

        $scores = $this->calcularMabac($matriz, $itens, $pesosNorm);

        DB::transaction(function () use ($analise, $scores) {
            MabacResultado::where('mabac_analise_id', $analise->id)->delete();

            arsort($scores['escores']);
            $ranking = 1;

            foreach ($scores['escores'] as $bancaId => $escore) {
                MabacResultado::create([
                    'mabac_analise_id' => $analise->id,
                    'banca_id'         => $bancaId,
                    'escore_final'     => round($escore, 4),
                    'ranking'          => $ranking++,
                    'detalhes_json'    => $scores['detalhes'][$bancaId] ?? null,
                ]);
            }
        });

        return $analise->load('resultados');
    }

    // ── Coleta de bancas ──────────────────────────────────────────────────────

    private function coletarBancas(MabacAnalise $analise): Collection
    {
        $query = Banca::with([
            'avaliacoes.notas.baremaItem',
            'trabalhoAcademico.linhaPesquisa',
        ])
            ->where('status', 'finalizada')
            ->whereNotNull('data_hora')
            ->whereDate('data_hora', '>=', $analise->periodo_inicio)
            ->whereDate('data_hora', '<=', $analise->periodo_fim)
            ->whereHas('avaliacoes', fn ($q) => $q->where('barema_id', $analise->barema_id)->where('status', 'enviada'));

        if ($analise->tipo_banca !== 'ambos') {
            $query->where('tipo', $analise->tipo_banca);
        }

        if ($analise->linha_pesquisa_id) {
            $query->whereHas('trabalhoAcademico', fn ($q) => $q->where('linha_pesquisa_id', $analise->linha_pesquisa_id));
        }

        return $query->get();
    }

    // ── Montagem da matriz de médias ──────────────────────────────────────────

    private function montarMatriz(Collection $bancas, Collection $itens): array
    {
        $matriz = [];

        foreach ($bancas as $banca) {
            $avaliacoesEnviadas = $banca->avaliacoes->where('status', 'enviada');

            if ($avaliacoesEnviadas->isEmpty()) {
                continue;
            }

            $medias = [];

            foreach ($itens as $item) {
                $notas = $avaliacoesEnviadas->flatMap(fn ($av) =>
                    $av->notas->where('barema_item_id', $item->id)->pluck('nota')
                )->filter(fn ($n) => $n !== null);

                $medias[$item->id] = $notas->isNotEmpty() ? $notas->avg() : 0.0;
            }

            $matriz[$banca->id] = $medias;
        }

        return $matriz;
    }

    // ── Algoritmo MABAC ───────────────────────────────────────────────────────

    private function calcularMabac(array $matriz, Collection $itens, Collection $pesosNorm): array
    {
        $bancaIds = array_keys($matriz);

        // Mínimo e máximo por critério (todos são critérios de benefício)
        $minMax = [];
        foreach ($itens as $item) {
            $vals = array_column($matriz, $item->id);
            $minMax[$item->id] = [
                'min' => min($vals),
                'max' => max($vals),
            ];
        }

        // Matriz normalizada n_ij e ponderada v_ij
        $vMatrix = [];

        foreach ($bancaIds as $bancaId) {
            foreach ($itens as $item) {
                $x   = $matriz[$bancaId][$item->id];
                $min = $minMax[$item->id]['min'];
                $max = $minMax[$item->id]['max'];
                $w   = $pesosNorm[$item->id] ?? 0;

                $nij = ($max - $min) > 0
                    ? ($x - $min) / ($max - $min)
                    : 0.0;

                $vMatrix[$bancaId][$item->id] = $w * ($nij + 1);
            }
        }

        // Fronteira de referência g_j (média geométrica por coluna)
        $gj = [];
        foreach ($itens as $item) {
            $produto = array_product(array_column($vMatrix, $item->id));
            $gj[$item->id] = pow($produto, 1 / count($bancaIds));
        }

        // Distâncias q_ij e escore final S_i
        $escores  = [];
        $detalhes = [];

        foreach ($bancaIds as $bancaId) {
            $distancias = [];
            $soma = 0.0;

            foreach ($itens as $item) {
                $qij = $vMatrix[$bancaId][$item->id] - $gj[$item->id];
                $distancias[$item->id] = round($qij, 6);
                $soma += $qij;
            }

            $escores[$bancaId]  = $soma;
            $detalhes[$bancaId] = [
                'distancias' => $distancias,
                'fronteira'  => $gj,
            ];
        }

        return ['escores' => $escores, 'detalhes' => $detalhes];
    }
}
