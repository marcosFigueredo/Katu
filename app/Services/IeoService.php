<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\BancaIndicador;
use App\Models\OrcidSnapshot;
use App\Models\OrientacaoAvaliacao;
use App\Models\TrabalhoAcademico;
use Carbon\Carbon;

class IeoService
{
    // Parâmetros de normalização para item_11_objetivo
    private const H_MAX  = 30;   // h-index acima disso = 100% dos pontos de H
    private const CIT_MAX = 1000; // citações acima disso = 100% dos pontos de citações
    private const PESO_H   = 50;  // pontos máximos pelo h-index
    private const PESO_CIT = 20;  // pontos máximos pelas citações
    private const PESO_B4  = 15;  // pontos por aderência temática (B4)
    private const PESO_B8  = 15;  // pontos por aderência à linha de pesquisa (B8)

    public function calcularItem11Objetivo(Banca $banca): float
    {
        $banca->loadMissing(['trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile', 'indicadores']);

        $trabalho      = $banca->trabalhoAcademico;
        $orientacao    = $trabalho?->orientacoes->firstWhere('tipo', 'orientador');
        $docenteProfile = $orientacao?->docenteProgramaVinculo?->docenteProfile;

        $snapshot = $docenteProfile
            ? OrcidSnapshot::where('docente_profile_id', $docenteProfile->id)->first()
            : null;

        $indicador = $banca->indicadores;

        $h   = min((int) ($snapshot?->indice_h ?? 0), self::H_MAX);
        $cit = min((int) ($snapshot?->total_citacoes ?? 0), self::CIT_MAX);

        $pontoH   = self::H_MAX > 0   ? round(($h   / self::H_MAX)   * self::PESO_H,   2) : 0;
        $pontoCit = self::CIT_MAX > 0 ? round(($cit / self::CIT_MAX) * self::PESO_CIT, 2) : 0;
        $pontoB4  = ($indicador?->b4 ?? false) ? self::PESO_B4 : 0;
        $pontoB8  = ($indicador?->b8 ?? false) ? self::PESO_B8 : 0;

        return round(min(100, $pontoH + $pontoCit + $pontoB4 + $pontoB8), 1);
    }

    public function calcular(OrientacaoAvaliacao $avaliacao): OrientacaoAvaliacao
    {
        $conv = fn (?int $x): ?float => $x !== null ? round((($x - 1) / 4) * 100, 2) : null;

        $cat1 = $this->mediaSegura([$avaliacao->item_11_objetivo, $conv($avaliacao->item_12)]);
        $cat2 = $this->mediaSegura([$conv($avaliacao->item_21), $conv($avaliacao->item_22)]);
        $cat3 = $this->mediaSegura([$conv($avaliacao->item_31), $conv($avaliacao->item_32)]);
        $cat4 = $this->mediaSegura([$conv($avaliacao->item_41), $conv($avaliacao->item_42)]);
        $cat5 = $this->mediaSegura([$conv($avaliacao->item_51), $conv($avaliacao->item_52)]);
        $cat6 = $this->mediaSegura([$conv($avaliacao->item_61), $conv($avaliacao->item_62)]);

        $ieo = $this->mediaSegura([$cat1, $cat2, $cat3, $cat4, $cat5, $cat6]);

        $avaliacao->update([
            'nota_cat1'    => $cat1,
            'nota_cat2'    => $cat2,
            'nota_cat3'    => $cat3,
            'nota_cat4'    => $cat4,
            'nota_cat5'    => $cat5,
            'nota_cat6'    => $cat6,
            'ieo_final'    => $ieo,
            'respondido_em' => Carbon::now(),
        ]);

        return $avaliacao->fresh();
    }

    private function mediaSegura(array $valores): ?float
    {
        $filtrados = array_filter($valores, fn ($v) => $v !== null);

        if (empty($filtrados)) {
            return null;
        }

        return round(array_sum($filtrados) / \count($filtrados), 2);
    }
}
