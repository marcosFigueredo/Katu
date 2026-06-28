<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $vinculos = DB::table('discente_programa_vinculos')
            ->leftJoin('trabalhos_academicos', 'trabalhos_academicos.discente_programa_vinculo_id', '=', 'discente_programa_vinculos.id')
            ->whereNull('trabalhos_academicos.id')
            ->select([
                'discente_programa_vinculos.id',
                'discente_programa_vinculos.programa_id',
                'discente_programa_vinculos.linha_pesquisa_id',
                'discente_programa_vinculos.nivel',
                'discente_programa_vinculos.data_ingresso',
            ])
            ->get();

        foreach ($vinculos as $vinculo) {
            DB::table('trabalhos_academicos')->insert([
                'programa_id' => $vinculo->programa_id,
                'discente_programa_vinculo_id' => $vinculo->id,
                'linha_pesquisa_id' => $vinculo->linha_pesquisa_id,
                'nivel' => $vinculo->nivel ?? 'mestrado',
                'status' => 'em_andamento',
                'data_inicio' => $vinculo->data_ingresso,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
