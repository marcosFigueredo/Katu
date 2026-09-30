<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_conformidade_rag', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_analise_id')
                ->constrained('documento_analises')
                ->cascadeOnDelete();

            $table->string('verificacao');

            $table->enum('status', ['processando', 'concluida', 'falhou'])
                ->default('processando');

            $table->string('veredito')->nullable();
            $table->text('justificativa')->nullable();
            $table->text('trecho_norma_citado')->nullable();
            $table->text('erro')->nullable();

            $table->timestamps();

            $table->unique(['documento_analise_id', 'verificacao'], 'doc_conform_rag_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_conformidade_rag');
    }
};
