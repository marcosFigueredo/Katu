<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            // FK depende do índice unique — precisa cair primeiro
            $table->dropForeign(['trabalho_academico_id']);
            $table->dropUnique(['trabalho_academico_id']);

            // Permite uma banca de qualificação E uma de defesa para o mesmo trabalho
            $table->unique(['trabalho_academico_id', 'tipo'], 'bancas_trabalho_tipo_unique');

            // Recoloca a FK (agora sem índice único simples)
            $table->foreign('trabalho_academico_id')
                ->references('id')->on('trabalhos_academicos')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dropForeign(['trabalho_academico_id']);
            $table->dropUnique('bancas_trabalho_tipo_unique');
            $table->unique('trabalho_academico_id');
            $table->foreign('trabalho_academico_id')
                ->references('id')->on('trabalhos_academicos')
                ->cascadeOnDelete();
        });
    }
};
