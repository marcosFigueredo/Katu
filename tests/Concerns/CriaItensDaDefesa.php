<?php

namespace Tests\Concerns;

use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoImpacto;

/** Tudo que o pedido de defesa exige do estudante além do espelho (impacto, atividades, produção, informações adicionais). */
trait CriaItensDaDefesa
{
    protected function criarItensDaDefesa(TrabalhoAcademico $trabalho): void
    {
        TrabalhoImpacto::create([
            'trabalho_academico_id' => $trabalho->id,
            'impacto_cientifico_aplicavel' => true,
            'impacto_cientifico_descricao' => 'Novo modelo para a vigilância epidemiológica.',
        ]);
    }
}
