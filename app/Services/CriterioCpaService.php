<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\CriterioCpa;
use Illuminate\Support\Collection;

class CriterioCpaService
{
    /**
     * Perguntas ativas do programa que valem para a etapa e o nível da banca.
     */
    public function perguntasPara(Banca $banca): Collection
    {
        $banca->loadMissing('trabalhoAcademico');
        $trabalho = $banca->trabalhoAcademico;

        return CriterioCpa::query()
            ->where('programa_id', $trabalho->programa_id)
            ->where('ativo', true)
            ->where(fn ($query) => $query->whereNull('etapa')->orWhere('etapa', $banca->tipo))
            ->where(fn ($query) => $query->whereNull('nivel')->orWhere('nivel', $trabalho->nivel))
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();
    }
}
