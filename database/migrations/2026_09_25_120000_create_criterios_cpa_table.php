<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_cpa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('etapa', 30);
            $table->string('tipo_evidencia', 30);
            $table->boolean('obrigatorio')->default(true);
            $table->string('nivel', 20)->nullable();
            $table->unsignedInteger('ordem')->default(1);
            $table->string('versao', 20)->default('1.0');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['programa_id', 'etapa', 'codigo', 'versao'], 'criterios_cpa_programa_codigo_versao_unique');
            $table->index(['programa_id', 'etapa', 'nivel', 'ativo'], 'criterios_cpa_aplicacao_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_cpa');
    }
};
