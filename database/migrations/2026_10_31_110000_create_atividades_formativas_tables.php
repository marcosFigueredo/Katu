<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Atividades formativas: a lista é definida pela Secretaria do programa; o estudante lança as realizadas. */
    public function up(): void
    {
        Schema::create('atividades_formativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->text('detalhes')->nullable();
            $table->unsignedTinyInteger('quantidade_exigida')->default(1);
            $table->enum('etapa', ['qualificacao', 'defesa'])->default('defesa');
            $table->enum('informada_por', ['estudante', 'secretaria'])->default('estudante');
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('atividades_realizadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->constrained('trabalhos_academicos')->cascadeOnDelete();
            $table->foreignId('atividade_formativa_id')->constrained('atividades_formativas')->restrictOnDelete();
            $table->string('descricao', 1000);
            $table->string('link', 500)->nullable();
            $table->enum('conferencia', ['confirmada', 'nao_confirmada'])->nullable();
            $table->foreignId('conferida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conferida_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades_realizadas');
        Schema::dropIfExists('atividades_formativas');
    }
};
