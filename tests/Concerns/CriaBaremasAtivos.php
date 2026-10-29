<?php

namespace Tests\Concerns;

use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\Programa;

/** Baremas ativos mínimos (1 seção, 2 itens de 0–10) para os fluxos que exigem barema cadastrado. */
trait CriaBaremasAtivos
{
    protected function criarBaremasAtivos(
        Programa $programa,
        array $tipos = ['qualificacao', 'qualificacao_confidencial', 'defesa', 'defesa_confidencial', 'autoavaliacao_orientador'],
        string $nivel = 'ambos',
    ): void {
        foreach ($tipos as $tipo) {
            $barema = Barema::create([
                'programa_id' => $programa->id,
                'nome' => 'Barema '.Barema::TIPOS[$tipo],
                'tipo' => $tipo,
                'nivel' => $nivel,
                'versao' => '1',
                'ativo' => true,
            ]);
            $secao = BaremaSecao::create(['barema_id' => $barema->id, 'nome' => 'Seção', 'peso' => 1, 'ordem' => 1, 'ativo' => true]);
            foreach ([1, 2] as $ordem) {
                BaremaItem::create([
                    'barema_secao_id' => $secao->id, 'nome' => "Item {$ordem} de ".Barema::TIPOS[$tipo],
                    'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ordem' => $ordem, 'ativo' => true,
                ]);
            }
        }
    }
}
