<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_regras_banca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_curso_id')->constrained('programa_cursos')->cascadeOnDelete();
            $table->string('etapa', 20);
            $table->unsignedTinyInteger('min_avaliadores');
            $table->unsignedTinyInteger('max_avaliadores');
            $table->unsignedTinyInteger('min_internos')->default(0);
            $table->unsignedTinyInteger('min_externos_instituicao')->default(0);
            $table->timestamps();
            $table->unique(['programa_curso_id', 'etapa'], 'curso_regras_banca_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_regras_banca');
    }
};
