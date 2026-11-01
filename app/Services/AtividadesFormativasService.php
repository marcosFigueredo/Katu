<?php

namespace App\Services;

use App\Models\AtividadeFormativa;
use App\Models\TrabalhoAcademico;
use Illuminate\Support\Collection;

/** Atividades formativas exigidas em cada pedido e o que ainda falta. */
class AtividadesFormativasService
{
    /** Atividades ativas do programa exigidas no pedido: a qualificação pede as dela; a defesa pede todas. */
    public function exigidas(TrabalhoAcademico $trabalho, string $tipoBanca): Collection
    {
        return AtividadeFormativa::where('programa_id', $trabalho->programa_id)
            ->where('ativo', true)
            ->when($tipoBanca === 'qualificacao', fn ($q) => $q->where('etapa', 'qualificacao'))
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();
    }

    /**
     * Uma frase por atividade abaixo da quantidade exigida, ex.: "Atividade Extensionista (1 de 2)".
     * Conta os lançamentos não recusados pela Secretaria (ou só os confirmados, na homologação).
     * Para o estudante, só as atividades que ele mesmo informa.
     *
     * @return list<string>
     */
    public function faltantes(TrabalhoAcademico $trabalho, string $tipoBanca, bool $soConfirmadas = false): array
    {
        $atividades = $this->exigidas($trabalho, $tipoBanca)
            ->when(! $soConfirmadas, fn ($c) => $c->where('informada_por', 'estudante'));

        $contagem = $trabalho->atividadesRealizadas()
            ->whereIn('atividade_formativa_id', $atividades->pluck('id'))
            ->when(
                $soConfirmadas,
                fn ($q) => $q->where('conferencia', 'confirmada'),
                fn ($q) => $q->where(fn ($q) => $q->whereNull('conferencia')->orWhere('conferencia', '!=', 'nao_confirmada')),
            )
            ->selectRaw('atividade_formativa_id, count(*) as total')
            ->groupBy('atividade_formativa_id')
            ->pluck('total', 'atividade_formativa_id');

        return $atividades
            ->filter(fn (AtividadeFormativa $a) => (int) ($contagem[$a->id] ?? 0) < $a->quantidade_exigida)
            ->map(fn (AtividadeFormativa $a) => "{$a->nome} (".(int) ($contagem[$a->id] ?? 0)." de {$a->quantidade_exigida})")
            ->values()
            ->all();
    }

    /** Há banca do trabalho aguardando homologação? Então os lançamentos ficam travados. */
    public function travado(TrabalhoAcademico $trabalho): bool
    {
        return $trabalho->bancas()
            ->where('status', '!=', 'cancelada')
            ->where('homologacao_status', 'aguardando')
            ->exists();
    }
}
