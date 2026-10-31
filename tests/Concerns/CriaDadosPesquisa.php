<?php

namespace Tests\Concerns;

use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDadosPesquisa;

/** Dados da pesquisa (código, dados e ética) já informados: exigidos para enviar o pedido à homologação. */
trait CriaDadosPesquisa
{
    protected function criarDadosPesquisa(TrabalhoAcademico $trabalho): TrabalhoDadosPesquisa
    {
        return TrabalhoDadosPesquisa::create([
            'trabalho_academico_id' => $trabalho->id,
            'codigo' => 'nao_houve',
            'dados_tipos' => [],
            'dados_disponibilidade' => 'nao_se_aplica',
            'seres_humanos' => false,
            'animais' => false,
            'preenchido_em' => now(),
        ]);
    }
}
