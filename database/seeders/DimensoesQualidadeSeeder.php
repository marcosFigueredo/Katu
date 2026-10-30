<?php

namespace Database\Seeders;

use App\Models\DimensaoQualidade;
use Illuminate\Database\Seeder;

/** As 8 dimensões já entram na migration; este seeder só garante que existam (idempotente). */
class DimensoesQualidadeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DimensaoQualidade::PADRAO as $dimensao) {
            DimensaoQualidade::firstOrCreate(['codigo' => $dimensao['codigo']], $dimensao);
        }
    }
}
