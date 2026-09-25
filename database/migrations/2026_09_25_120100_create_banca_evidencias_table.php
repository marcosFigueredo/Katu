<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banca_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->constrained('bancas')->cascadeOnDelete();
            $table->foreignId('criterio_cpa_id')->constrained('criterios_cpa')->cascadeOnDelete();
            $table->string('status', 30)->default('ausente');
            $table->text('valor_texto')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('arquivo_nome')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('informado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('informado_em')->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validado_em')->nullable();
            $table->timestamps();

            $table->unique(['banca_id', 'criterio_cpa_id'], 'banca_evidencia_banca_criterio_unique');
            $table->index(['banca_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banca_evidencias');
    }
};
