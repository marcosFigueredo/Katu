<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Projeto de pesquisa do docente permanente (um ativo por vez; os concluídos ficam no histórico).
        Schema::create('projetos_pesquisa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_programa_vinculo_id')->constrained('docente_programa_vinculos')->cascadeOnDelete();
            $table->string('titulo', 500);
            $table->text('descricao');
            $table->date('inicio_em');
            $table->date('fim_em')->nullable();
            $table->enum('situacao', ['ativo', 'concluido'])->default('ativo');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Vínculo do trabalho ao projeto do orientador; nunca se apaga (conferência posterior pela CPA).
        Schema::create('trabalho_projeto_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->constrained('trabalhos_academicos')->cascadeOnDelete();
            $table->foreignId('projeto_pesquisa_id')->constrained('projetos_pesquisa')->cascadeOnDelete();
            $table->enum('motivo', ['orientador', 'troca_orientador', 'novo_projeto']);
            $table->date('inicio_em');
            $table->date('fim_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('objetivo')->nullable();
            $table->boolean('mantem_objetivo')->nullable();
            $table->dateTime('objetivo_informado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_projeto_vinculos');
        Schema::dropIfExists('projetos_pesquisa');
    }
};
