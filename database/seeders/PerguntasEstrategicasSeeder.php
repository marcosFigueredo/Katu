<?php

namespace Database\Seeders;

use App\Models\PerguntaEstrategica;
use App\Models\Programa;
use Illuminate\Database\Seeder;

class PerguntasEstrategicasSeeder extends Seeder
{
    /**
     * Semeia as 3 perguntas estratégicas oficiais do item 2.1 da avaliação
     * quadrienal da CAPES, já vinculadas aos indicadores automáticos do Katu.
     */
    public function run(): void
    {
        $programaId = Programa::where('sigla', 'PPGMSB')->value('id');

        $this->criar(
            $programaId,
            '2.1',
            'Os trabalhos de conclusão possuem aderência às áreas de concentração e linhas de pesquisa?',
            [
                ['indicador_codigo' => 'A17', 'direcao' => 'maior_melhor', 'limiar_verde' => 80, 'limiar_amarelo' => 50],
            ]
        );

        $this->criar(
            $programaId,
            '2.1',
            'Os trabalhos possuem produtos resultantes e estes geraram impacto?',
            [
                ['indicador_codigo' => 'A18', 'direcao' => 'maior_melhor', 'limiar_verde' => 80, 'limiar_amarelo' => 50],
            ]
        );

        $this->criar(
            $programaId,
            '2.1',
            'Como são as bancas examinadoras em termos de qualificação, exogenia, internacionalização e envolvimento de docentes de outras IES?',
            [
                ['indicador_codigo' => 'A09', 'direcao' => 'maior_melhor', 'limiar_verde' => 0.75, 'limiar_amarelo' => 0.5],
            ]
        );
    }

    private function criar(?int $programaId, string $itemCapes, string $texto, array $indicadores): void
    {
        $pergunta = PerguntaEstrategica::firstOrCreate(
            ['programa_id' => $programaId, 'texto' => $texto],
            ['item_capes' => $itemCapes, 'origem' => 'capes', 'ativa' => true]
        );

        if ($pergunta->indicadores()->doesntExist()) {
            foreach ($indicadores as $indicador) {
                $pergunta->indicadores()->create($indicador);
            }
        }
    }
}
