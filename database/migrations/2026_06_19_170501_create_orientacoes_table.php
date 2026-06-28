<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orientacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabalho_academico_id')
                ->constrained('trabalhos_academicos')
                ->cascadeOnDelete();

            $table->foreignId('docente_programa_vinculo_id')
                ->constrained('docente_programa_vinculos')
                ->cascadeOnDelete();

            $table->enum('tipo', [
                'orientador',
                'coorientador',
            ])->default('orientador');

            $table->unsignedTinyInteger('ordem')->default(1);

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'trabalho_academico_id',
                'docente_programa_vinculo_id',
            ], 'orientacoes_trab_doc_unique');

            $table->index([
                'trabalho_academico_id',
                'tipo',
                'ativo',
            ], 'orientacoes_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientacoes');
    }
};
