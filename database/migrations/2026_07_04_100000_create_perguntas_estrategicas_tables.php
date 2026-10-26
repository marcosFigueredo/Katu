<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perguntas_estrategicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')
                ->nullable()
                ->constrained('programas')
                ->cascadeOnDelete();
            $table->string('item_capes', 20)->nullable();
            $table->text('texto');
            $table->enum('origem', ['capes', 'comissao'])->default('comissao');
            $table->boolean('ativa')->default(true);
            $table->foreignId('criado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pergunta_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pergunta_estrategica_id')
                ->constrained('perguntas_estrategicas')
                ->cascadeOnDelete();
            $table->string('indicador_codigo', 20);
            $table->enum('direcao', ['maior_melhor', 'menor_melhor'])->default('maior_melhor');
            $table->float('limiar_verde')->nullable();
            $table->float('limiar_amarelo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pergunta_indicadores');
        Schema::dropIfExists('perguntas_estrategicas');
    }
};
