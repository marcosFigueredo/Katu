<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mabac_analises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barema_id')->constrained('baremas');
            $table->foreignId('linha_pesquisa_id')->nullable()->constrained('linhas_pesquisa')->nullOnDelete();
            $table->string('nome');
            $table->enum('tipo_banca', ['qualificacao', 'defesa', 'ambos'])->default('ambos');
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->string('criado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('mabac_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mabac_analise_id')->constrained('mabac_analises')->cascadeOnDelete();
            $table->foreignId('banca_id')->constrained('bancas')->cascadeOnDelete();
            $table->float('escore_final');
            $table->unsignedSmallInteger('ranking');
            $table->json('detalhes_json')->nullable();
            $table->timestamps();

            $table->unique(['mabac_analise_id', 'banca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mabac_resultados');
        Schema::dropIfExists('mabac_analises');
    }
};
