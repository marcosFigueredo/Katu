<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\Barema;

/** Quais baremas cada banca exige e quais o programa ainda não tem ativos. */
class BaremasDaBancaService
{
    /** @return list<string> */
    public function tiposExigidos(Banca $banca): array
    {
        return $banca->tipo === 'defesa'
            ? ['defesa', 'defesa_confidencial', 'autoavaliacao_orientador']
            : ['qualificacao', 'qualificacao_confidencial'];
    }

    public function baremaAtivo(Banca $banca, string $tipo): ?Barema
    {
        $trabalho = $banca->trabalhoAcademico;
        if (! $trabalho) {
            return null;
        }

        return Barema::where('programa_id', $trabalho->programa_id)
            ->where('tipo', $tipo)
            ->where('ativo', true)
            ->whereIn('nivel', [$trabalho->nivel ?? 'mestrado', 'ambos'])
            ->orderByDesc('id')
            ->first();
    }

    /** @return list<string> mensagens "Barema de … não cadastrado." */
    public function faltantes(Banca $banca): array
    {
        $faltantes = [];
        foreach ($this->tiposExigidos($banca) as $tipo) {
            if ($this->baremaAtivo($banca, $tipo) === null) {
                $faltantes[] = 'Barema de '.Barema::TIPOS[$tipo].' não cadastrado.';
            }
        }

        return $faltantes;
    }
}
