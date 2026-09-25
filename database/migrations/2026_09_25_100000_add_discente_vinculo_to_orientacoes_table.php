<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientacoes', function (Blueprint $table) {
            $table->foreignId('discente_programa_vinculo_id')
                ->nullable()
                ->after('trabalho_academico_id')
                ->constrained('discente_programa_vinculos')
                ->cascadeOnDelete();

            $table->index([
                'discente_programa_vinculo_id',
                'tipo',
                'ativo',
            ], 'orientacoes_discente_lookup_idx');
        });

        DB::table('orientacoes')
            ->whereNull('discente_programa_vinculo_id')
            ->select(['id', 'trabalho_academico_id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $orientacao): void {
                $discenteVinculoId = DB::table('trabalhos_academicos')
                    ->where('id', $orientacao->trabalho_academico_id)
                    ->value('discente_programa_vinculo_id');

                DB::table('orientacoes')
                    ->where('id', $orientacao->id)
                    ->update(['discente_programa_vinculo_id' => $discenteVinculoId]);
            });
    }

    public function down(): void
    {
        Schema::table('orientacoes', function (Blueprint $table) {
            $table->dropIndex('orientacoes_discente_lookup_idx');
            $table->dropForeign(['discente_programa_vinculo_id']);
            $table->dropColumn('discente_programa_vinculo_id');
        });
    }
};
