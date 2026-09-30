<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_conhecimento_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_documento_referencia_id')
                ->constrained('programa_documentos_referencia')
                ->cascadeOnDelete();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->enum('tipo', ['normas', 'template']);
            $table->unsignedInteger('pagina');
            $table->unsignedInteger('ordem');
            $table->text('conteudo');
            $table->json('embedding');

            $table->timestamps();

            $table->index(['programa_id', 'tipo'], 'base_conhec_chunks_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_conhecimento_chunks');
    }
};
