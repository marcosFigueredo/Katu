<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabalhos_academicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->foreignId('discente_programa_vinculo_id')
                ->constrained('discente_programa_vinculos')
                ->cascadeOnDelete();

            $table->foreignId('linha_pesquisa_id')
                ->nullable()
                ->constrained('linhas_pesquisa')
                ->nullOnDelete();

            $table->string('titulo')->nullable();

            $table->text('resumo')->nullable();

            $table->string('palavras_chave')->nullable();

            $table->enum('nivel', ['mestrado', 'doutorado'])
                ->default('mestrado');

            $table->enum('status', [
                'em_andamento',
                'qualificado',
                'defendido',
                'concluido',
                'cancelado',
            ])->default('em_andamento');

            $table->date('data_inicio')->nullable();

            $table->date('data_prevista_qualificacao')->nullable();

            $table->date('data_prevista_defesa')->nullable();

            $table->timestamps();

            $table->unique('discente_programa_vinculo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalhos_academicos');
    }
};