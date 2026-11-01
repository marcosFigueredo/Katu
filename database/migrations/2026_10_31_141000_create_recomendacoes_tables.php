<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Recomendações da banca de qualificação e as respostas do estudante na defesa (dimensão 5). */
    public function up(): void
    {
        Schema::create('banca_recomendacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->constrained('bancas')->cascadeOnDelete();
            $table->foreignId('banca_membro_id')->constrained('banca_membros')->cascadeOnDelete();
            $table->string('texto', 1000);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('recomendacao_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_recomendacao_id')->unique()->constrained('banca_recomendacoes')->cascadeOnDelete();
            $table->enum('situacao', ['atendida', 'parcial', 'nao_atendida']);
            $table->string('onde', 500)->nullable();
            $table->text('justificativa')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recomendacao_respostas');
        Schema::dropIfExists('banca_recomendacoes');
    }
};
