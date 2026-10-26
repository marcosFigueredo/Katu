<?php

namespace App\Services;

use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\PerguntaEstrategica;

class PerguntaEstrategicaService
{
    private const ORDEM_STATUS = [
        'vermelho'    => 0,
        'amarelo'     => 1,
        'verde'       => 2,
        'sem_limiar'  => 3,
        'sem_dado'    => 4,
    ];

    /**
     * Avalia uma pergunta estratégica contra os indicadores vinculados a ela,
     * usando o período de autoavaliação informado (ou o mais recente concluído).
     */
    public function avaliar(PerguntaEstrategica $pergunta, ?AutoavaliacaoPeriodo $periodo = null): array
    {
        $pergunta->loadMissing('indicadores');

        if ($pergunta->indicadores->isEmpty()) {
            return ['status' => 'sem_indicador', 'indicadores' => []];
        }

        if (! $periodo) {
            return ['status' => 'sem_dado', 'indicadores' => []];
        }

        $indicadoresPeriodo = AutoavaliacaoIndicador::where('autoavaliacao_periodo_id', $periodo->id)
            ->whereIn('codigo', $pergunta->indicadores->pluck('indicador_codigo'))
            ->get()
            ->keyBy('codigo');

        $detalhes = $pergunta->indicadores->map(function ($vinculo) use ($indicadoresPeriodo) {
            $indicador = $indicadoresPeriodo->get($vinculo->indicador_codigo);
            $valor     = $indicador?->valor_numerico;

            return [
                'codigo' => $vinculo->indicador_codigo,
                'nome'   => $indicador?->nome,
                'valor'  => $valor,
                'status' => $this->statusIndicador($valor, $vinculo),
            ];
        });

        $piorStatus = $detalhes
            ->pluck('status')
            ->sort(fn ($a, $b) => self::ORDEM_STATUS[$a] <=> self::ORDEM_STATUS[$b])
            ->first();

        return ['status' => $piorStatus, 'indicadores' => $detalhes->all()];
    }

    private function statusIndicador(?float $valor, $vinculo): string
    {
        if ($valor === null) {
            return 'sem_dado';
        }

        if ($vinculo->limiar_verde === null || $vinculo->limiar_amarelo === null) {
            return 'sem_limiar';
        }

        $maiorMelhor = $vinculo->direcao === 'maior_melhor';

        if ($maiorMelhor ? $valor >= $vinculo->limiar_verde : $valor <= $vinculo->limiar_verde) {
            return 'verde';
        }

        if ($maiorMelhor ? $valor >= $vinculo->limiar_amarelo : $valor <= $vinculo->limiar_amarelo) {
            return 'amarelo';
        }

        return 'vermelho';
    }
}
