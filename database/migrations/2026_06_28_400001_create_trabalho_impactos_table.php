<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabalho_impactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->unique()->constrained('trabalhos_academicos')->cascadeOnDelete();

            $table->boolean('impacto_cientifico_aplicavel')->default(false);
            $table->text('impacto_cientifico_descricao')->nullable();

            $table->boolean('impacto_metodologico_aplicavel')->default(false);
            $table->text('impacto_metodologico_descricao')->nullable();

            $table->boolean('impacto_tecnologico_aplicavel')->default(false);
            $table->text('impacto_tecnologico_descricao')->nullable();

            $table->boolean('impacto_social_aplicavel')->default(false);
            $table->text('impacto_social_descricao')->nullable();

            $table->boolean('impacto_ambiental_aplicavel')->default(false);
            $table->text('impacto_ambiental_descricao')->nullable();

            $table->boolean('impacto_formativo_aplicavel')->default(false);
            $table->text('impacto_formativo_descricao')->nullable();

            $table->boolean('impacto_institucional_aplicavel')->default(false);
            $table->text('impacto_institucional_descricao')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_impactos');
    }
};
