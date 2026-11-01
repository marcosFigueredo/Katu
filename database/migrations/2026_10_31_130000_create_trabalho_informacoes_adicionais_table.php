<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Informações adicionais da submissão da defesa (conflito de interesses, dados, amostras, ética, consentimento, IA, originalidade). */
    public function up(): void
    {
        Schema::create('trabalho_informacoes_adicionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->unique()->constrained('trabalhos_academicos')->cascadeOnDelete();
            $table->boolean('conflito_interesses');
            $table->text('conflito_descricao')->nullable();
            $table->enum('disponibilidade_dados', ['nenhum_dado_novo', 'no_trabalho', 'sob_solicitacao', 'repositorio_publico']);
            $table->string('dados_repositorio', 500)->nullable();
            $table->boolean('amostras_fisicas');
            $table->text('amostras_declaracao')->nullable();
            $table->boolean('aprovacao_etica');
            $table->string('aprovacao_etica_path')->nullable();
            $table->string('aprovacao_etica_nome')->nullable();
            $table->boolean('participantes');
            $table->string('certidao_etica_path')->nullable();
            $table->string('certidao_etica_nome')->nullable();
            $table->boolean('uso_ia');
            $table->string('ia_ferramentas', 500)->nullable();
            $table->text('ia_etapas')->nullable();
            $table->boolean('originalidade_confirmada');
            $table->timestamp('preenchido_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_informacoes_adicionais');
    }
};
