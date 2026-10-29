<?php

namespace Database\Seeders;

use App\Models\AreaCnpq;
use App\Models\SetorCnae;
use Illuminate\Database\Seeder;

/**
 * Carrega as listas oficiais usadas nos dados para o Lattes (database/data):
 * - areas_cnpq.csv: Tabela de Áreas do Conhecimento do CNPq (relatório de 30/06/2025), até subárea;
 * - setores_cnae.csv: CNAE 2.0, divisões (IBGE, servicodados.ibge.gov.br/api/v2/cnae/divisoes).
 * Só cria o que falta: não desfaz edições nem reativa itens desativados pela Secretaria.
 */
class TabelasLattesSeeder extends Seeder
{
    public function run(): void
    {
        $ids = [];
        foreach ($this->linhas('areas_cnpq.csv') as [$codigo, $nome, $nivel, $codigoPai]) {
            $area = AreaCnpq::firstOrCreate(
                ['codigo' => $codigo],
                ['nome' => $nome, 'nivel' => $nivel, 'pai_id' => $codigoPai !== '' ? ($ids[$codigoPai] ?? null) : null, 'ativo' => true],
            );
            $ids[$codigo] = $area->id;
        }

        foreach ($this->linhas('setores_cnae.csv') as [$codigo, $nome, $secao]) {
            SetorCnae::firstOrCreate(['codigo' => $codigo], ['nome' => $nome, 'secao' => $secao ?: null, 'ativo' => true]);
        }
    }

    /** @return iterable<list<string>> */
    private function linhas(string $arquivo): iterable
    {
        $handle = fopen(database_path("data/{$arquivo}"), 'r');
        fgetcsv($handle, 0, ';'); // cabeçalho
        while (($linha = fgetcsv($handle, 0, ';')) !== false) {
            yield array_map('trim', $linha);
        }
        fclose($handle);
    }
}
