<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_analise_ollamas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_analise_id')
                ->constrained('documento_analises')
                ->cascadeOnDelete();

            $table->string('modelo')->nullable();

            $table->longText('prompt')->nullable();

            $table->longText('resposta')->nullable();

            $table->enum('status', [
                'aguardando',
                'processando',
                'concluida',
                'falhou',
            ])->default('aguardando');

            $table->text('erro')->nullable();

            $table->timestamp('gerado_em')->nullable();

            $table->timestamps();

            $table->index([
                'documento_analise_id',
                'status',
            ], 'doc_ollama_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_analise_ollamas');
    }
};
