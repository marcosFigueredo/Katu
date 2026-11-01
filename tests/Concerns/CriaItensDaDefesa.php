<?php

namespace Tests\Concerns;

use App\Models\AtividadeFormativa;
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

        AtividadeFormativa::criarPadroes($trabalho->programa);
        AtividadeFormativa::where('programa_id', $trabalho->programa_id)->where('informada_por', 'estudante')->get()
            ->each(fn (AtividadeFormativa $a) => $trabalho->atividadesRealizadas()->create([
                'atividade_formativa_id' => $a->id,
                'descricao' => "Realizada: {$a->nome}",
            ]));

        $trabalho->publicacoes()->create([
            'tipo' => 'artigo',
            'titulo' => 'Artigo derivado da dissertação',
            'ano' => 2025,
            'veiculo' => 'Annals of Epidemiology',
            'doi' => '10.1016/j.annepidem.2025.06.012',
        ]);
        $trabalho->update(['producao_confirmada_em' => now()]);
    }
}
