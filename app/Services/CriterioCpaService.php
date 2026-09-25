<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\CriterioCpa;
use Illuminate\Support\Collection;

class CriterioCpaService
{
    public function criteriosAtivosPara(Banca $banca): Collection
    {
        $banca->loadMissing('trabalhoAcademico');

        return CriterioCpa::query()
            ->where('programa_id', $banca->trabalhoAcademico->programa_id)
            ->where('etapa', $banca->tipo)
            ->where('ativo', true)
            ->where(function ($query) use ($banca) {
                $query->whereNull('nivel')
                    ->orWhere('nivel', $banca->trabalhoAcademico->nivel);
            })
            ->orderBy('ordem')
            ->orderBy('codigo')
            ->get();
    }

    public function pendenciasObrigatorias(Banca $banca): Collection
    {
        $criterios = $this->criteriosAtivosPara($banca);
        $evidencias = $banca->evidencias()->get()->keyBy('criterio_cpa_id');

        return $criterios
            ->where('obrigatorio', true)
            ->filter(function (CriterioCpa $criterio) use ($evidencias) {
                $evidencia = $evidencias->get($criterio->id);

                return ! $evidencia
                    || ($evidencia->status !== 'validada'
                        && ! ($evidencia->status === 'nao_aplicavel' && filled($evidencia->observacoes)));
            })
            ->values();
    }
}
