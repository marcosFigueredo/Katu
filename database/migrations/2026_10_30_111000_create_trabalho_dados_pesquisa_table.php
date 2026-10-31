<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabalho_dados_pesquisa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->unique()->constrained('trabalhos_academicos')->cascadeOnDelete();

            $table->enum('codigo', ['nao_houve', 'publico', 'sob_solicitacao', 'nao_compartilhavel']);
            $table->string('codigo_link', 500)->nullable();
            $table->text('codigo_justificativa')->nullable();

            $table->json('dados_tipos')->nullable();
            $table->enum('dados_disponibilidade', ['nao_se_aplica', 'publico', 'no_trabalho', 'sob_solicitacao', 'nao_compartilhavel']);
            $table->string('dados_link', 500)->nullable();
            $table->text('dados_justificativa')->nullable();

            $table->boolean('seres_humanos');
            $table->string('caae', 20)->nullable();
            $table->string('parecer_numero', 30)->nullable();
            $table->enum('tcle', ['termo', 'dispensa'])->nullable();
            $table->text('tcle_justificativa')->nullable();

            $table->boolean('animais');
            $table->string('ceua_protocolo', 60)->nullable();

            $table->timestamp('preenchido_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_dados_pesquisa');
    }
};
