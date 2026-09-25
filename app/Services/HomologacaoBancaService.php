<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomologacaoBancaService
{
    public function __construct(private readonly CriterioCpaService $criterioCpa)
    {
    }

    public function homologar(Banca $banca, User $responsavel): void
    {
        $pendencias = $this->criterioCpa->pendenciasObrigatorias($banca);

        if ($pendencias->isNotEmpty()) {
            $itens = $pendencias->map(fn ($criterio) => $criterio->codigo . ' — ' . $criterio->titulo)->implode('; ');
            throw ValidationException::withMessages([
                'homologacao' => 'Não é possível homologar. Evidências pendentes: ' . $itens,
            ]);
        }

        if ($banca->status === 'cancelada') {
            throw ValidationException::withMessages([
                'homologacao' => 'Uma banca cancelada não pode ser homologada.',
            ]);
        }

        DB::transaction(function () use ($banca, $responsavel) {
            $banca->update([
                'homologacao_status' => 'homologada',
                'homologacao_observacoes' => null,
                'homologada_por' => $responsavel->id,
                'homologada_em' => now(),
                'devolvida_por' => null,
                'devolvida_em' => null,
                'libera_envio_trabalho' => true,
            ]);
        });
    }

    public function devolver(Banca $banca, User $responsavel, string $observacoes): void
    {
        DB::transaction(function () use ($banca, $responsavel, $observacoes) {
            $banca->update([
                'homologacao_status' => 'devolvida',
                'homologacao_observacoes' => $observacoes,
                'homologada_por' => null,
                'homologada_em' => null,
                'devolvida_por' => $responsavel->id,
                'devolvida_em' => now(),
                'libera_envio_trabalho' => false,
            ]);
        });
    }
}
