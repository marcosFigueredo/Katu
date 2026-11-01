<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\BancaAvaliacao;
use Illuminate\Support\Collection;

/**
 * Plágio identificado pela banca no formulário sigiloso (spec, dimensão 8 › Regra do plágio):
 * unanimidade reprova; marcação parcial é pendência que bloqueia a finalização, a ata e a folha.
 */
class IntegridadeBancaService
{
    public const PENDENCIA = 'Há uma pendência de integridade a ser resolvida pela banca. Os avaliadores precisam se alinhar antes da finalização: quem marcou plágio desmarca, ou os demais também marcam, no quadro "Plágio" da tela da banca.';

    /** 'nenhuma' | 'parcial' | 'unanime' — só avaliações confidenciais enviadas dos avaliadores ativos. */
    public function situacao(Banca $banca): string
    {
        $avaliadores = $banca->avaliadores()->pluck('id');
        if ($avaliadores->isEmpty()) {
            return 'nenhuma';
        }

        $marcaram = $this->marcadas($banca, $avaliadores)->pluck('banca_membro_id')->unique();
        if ($marcaram->isEmpty()) {
            return 'nenhuma';
        }

        return $avaliadores->diff($marcaram)->isEmpty() ? 'unanime' : 'parcial';
    }

    /** Onde a banca indicou o plágio, sem autoria (visível aos avaliadores). */
    public function indicacoes(Banca $banca): Collection
    {
        return $this->marcadas($banca, $banca->avaliadores()->pluck('id'))->pluck('plagio_indicacao')->filter()->values();
    }

    /** Para o orientador: quem marcou plágio e onde (registro da banca). */
    public function indicacoesComAutoria(Banca $banca): Collection
    {
        $membros = $banca->avaliadores()->with('docenteProgramaVinculo.docenteProfile.user')->get()->keyBy('id');

        return $this->marcadas($banca, $membros->keys())->map(fn ($a) => [
            'avaliador' => $membros[$a->banca_membro_id]?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? 'Avaliador',
            'indicacao' => $a->plagio_indicacao,
        ])->values();
    }

    private function marcadas(Banca $banca, Collection $avaliadores): Collection
    {
        return BancaAvaliacao::where('banca_id', $banca->id)
            ->whereIn('banca_membro_id', $avaliadores)
            ->where('status', 'enviada')
            ->where('plagio_identificado', true)
            ->whereHas('barema', fn ($q) => $q->whereIn('tipo', ['qualificacao_confidencial', 'defesa_confidencial']))
            ->get(['banca_membro_id', 'plagio_indicacao']);
    }
}
