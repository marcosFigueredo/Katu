<?php

namespace App\Services;

use App\Models\Programa;
use App\Models\ProgramaCurso;
use Illuminate\Support\Facades\DB;

class CursoProgramaService
{
    public function criar(Programa $programa, string $nivel, string $modalidade, ?int $prazoQualificacao = null, ?int $prazoDefesa = null): ProgramaCurso
    {
        $padrao = ProgramaCurso::PADROES[$nivel];

        return DB::transaction(function () use ($programa, $nivel, $modalidade, $prazoQualificacao, $prazoDefesa, $padrao) {
            $curso = ProgramaCurso::create([
                'programa_id' => $programa->id,
                'nivel' => $nivel,
                'modalidade' => $modalidade,
                'prazo_qualificacao_meses' => $prazoQualificacao ?? $padrao['prazo_qualificacao_meses'],
                'prazo_defesa_meses' => $prazoDefesa ?? $padrao['prazo_defesa_meses'],
                'ativo' => true,
            ]);

            [$min, $max, $internos, $externos] = $padrao['regra'];
            foreach (ProgramaCurso::ETAPAS as $etapa) {
                $curso->regrasBanca()->create([
                    'etapa' => $etapa,
                    'min_avaliadores' => $min,
                    'max_avaliadores' => $max,
                    'min_internos' => $internos,
                    'min_externos_instituicao' => $externos,
                ]);
            }

            $this->sincronizarOfertas($programa);

            return $curso;
        });
    }

    public function sincronizarOfertas(Programa $programa): void
    {
        $niveis = $programa->cursos()->where('ativo', true)->pluck('nivel');

        $programa->update([
            'oferta_mestrado' => $niveis->contains('mestrado'),
            'oferta_doutorado' => $niveis->contains('doutorado'),
        ]);
    }
}
