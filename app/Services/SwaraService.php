<?php

namespace App\Services;

use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\SwaraJulgamento;
use App\Models\SwaraSessao;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SwaraService
{
    /**
     * Cria uma sessão SWARA e persiste os julgamentos calculados.
     *
     * @param  Barema  $barema
     * @param  string  $nome
     * @param  string|null  $descricao
     * @param  string|null  $aprovadoPor
     * @param  array  $ordemItemIds  IDs dos BaremaItem em ordem de importância (mais importante primeiro)
     * @param  array  $sJValues      s_j[itemId] => valor (0 para o primeiro, ≥0 para os demais)
     */
    public function criarSessao(
        Barema $barema,
        string $nome,
        ?string $descricao,
        ?string $aprovadoPor,
        array $ordemItemIds,
        array $sJValues
    ): SwaraSessao {
        $pesos = $this->calcular($ordemItemIds, $sJValues);

        return DB::transaction(function () use ($barema, $nome, $descricao, $aprovadoPor, $ordemItemIds, $sJValues, $pesos) {
            $sessao = SwaraSessao::create([
                'barema_id'    => $barema->id,
                'nome'         => $nome,
                'descricao'    => $descricao,
                'aprovado_por' => $aprovadoPor,
                'aprovado_em'  => $aprovadoPor ? Carbon::now() : null,
            ]);

            foreach ($ordemItemIds as $posicao => $itemId) {
                $ordem = $posicao + 1;
                SwaraJulgamento::create([
                    'swara_sessao_id' => $sessao->id,
                    'barema_item_id'  => $itemId,
                    'ordem'           => $ordem,
                    's_j'             => $sJValues[$itemId] ?? 0,
                    'k_j'             => $pesos[$itemId]['k_j'],
                    'q_j'             => $pesos[$itemId]['q_j'],
                    'w_j'             => $pesos[$itemId]['w_j'],
                ]);
            }

            return $sessao->load('julgamentos');
        });
    }

    /**
     * Calcula k_j, q_j, w_j para cada critério conforme o método SWARA.
     *
     * @param  array  $ordemItemIds  IDs em ordem de importância
     * @param  array  $sJValues      s_j por itemId (0 = igual ao anterior)
     * @return array  [itemId => ['k_j'=>…, 'q_j'=>…, 'w_j'=>…]]
     */
    public function calcular(array $ordemItemIds, array $sJValues): array
    {
        $resultado = [];
        $qAnterior = 1.0;

        foreach ($ordemItemIds as $posicao => $itemId) {
            if ($posicao === 0) {
                $k = 1.0;
                $q = 1.0;
            } else {
                $s = max(0.0, (float) ($sJValues[$itemId] ?? 0));
                $k = $s + 1.0;
                $q = $qAnterior / $k;
            }

            $resultado[$itemId] = ['k_j' => $k, 'q_j' => $q, 'w_j' => 0.0];
            $qAnterior = $q;
        }

        $somaQ = array_sum(array_column($resultado, 'q_j'));

        if ($somaQ > 0) {
            foreach ($resultado as $itemId => &$dados) {
                $dados['w_j'] = round($dados['q_j'] / $somaQ, 6);
            }
        }

        return $resultado;
    }

    /**
     * Aplica os pesos da sessão SWARA aos barema_itens (atualiza o campo peso).
     * O peso SWARA é normalizado; o sistema usa peso como fator relativo.
     * Multiplicamos por 10 para manter compatibilidade com a escala existente.
     */
    public function aplicar(SwaraSessao $sessao): void
    {
        $sessao->loadMissing('julgamentos');

        DB::transaction(function () use ($sessao) {
            foreach ($sessao->julgamentos as $julgamento) {
                BaremaItem::where('id', $julgamento->barema_item_id)
                    ->update(['peso' => round($julgamento->w_j * 10, 4)]);
            }

            $sessao->update(['aplicado_em' => Carbon::now()]);
        });
    }
}
