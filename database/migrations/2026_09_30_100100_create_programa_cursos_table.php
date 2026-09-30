<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nivel', 20);
            $table->string('modalidade', 20);
            $table->unsignedSmallInteger('prazo_qualificacao_meses');
            $table->unsignedSmallInteger('prazo_defesa_meses');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['programa_id', 'nivel', 'modalidade'], 'programa_cursos_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_cursos');
    }
};
