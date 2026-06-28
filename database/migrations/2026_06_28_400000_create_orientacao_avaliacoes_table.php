<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orientacao_avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->constrained('bancas')->cascadeOnDelete();
            $table->foreignId('discente_programa_vinculo_id')->constrained('discente_programa_vinculos')->cascadeOnDelete();
            $table->foreignId('docente_programa_vinculo_id')->constrained('docente_programa_vinculos')->cascadeOnDelete();

            // Cat1: Competência científica
            $table->float('item_11_objetivo')->nullable(); // calculado pelo sistema (0–100)
            $table->tinyInteger('item_12')->nullable();    // Likert 1–5

            // Cat2: Disponibilidade e comprometimento
            $table->tinyInteger('item_21')->nullable();
            $table->tinyInteger('item_22')->nullable();

            // Cat3: Contribuição à qualidade do trabalho
            $table->tinyInteger('item_31')->nullable();
            $table->tinyInteger('item_32')->nullable();

            // Cat4: Comunicação e feedback
            $table->tinyInteger('item_41')->nullable();
            $table->tinyInteger('item_42')->nullable();

            // Cat5: Suporte ao desenvolvimento profissional
            $table->tinyInteger('item_51')->nullable();
            $table->tinyInteger('item_52')->nullable();

            // Cat6: Ética e responsabilidade
            $table->tinyInteger('item_61')->nullable();
            $table->tinyInteger('item_62')->nullable();

            // Scores agregados
            $table->float('nota_cat1')->nullable();
            $table->float('nota_cat2')->nullable();
            $table->float('nota_cat3')->nullable();
            $table->float('nota_cat4')->nullable();
            $table->float('nota_cat5')->nullable();
            $table->float('nota_cat6')->nullable();
            $table->float('ieo_final')->nullable();

            $table->timestamp('respondido_em')->nullable();
            $table->timestamps();

            $table->unique(['banca_id', 'discente_programa_vinculo_id'], 'oa_banca_discente_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientacao_avaliacoes');
    }
};
