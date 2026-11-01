<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Período de avaliação do programa, com as faixas das 5 classes de qualidade.
        Schema::create('periodos_qualidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->date('inicio_em');
            $table->date('fim_em');
            $table->decimal('corte_alta', 4, 2)->default(8.5);
            $table->decimal('corte_boa', 4, 2)->default(7.0);
            $table->decimal('corte_suficiente', 4, 2)->default(5.5);
            $table->decimal('corte_pouca', 4, 2)->default(4.0);
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Pesos definidos pela CPA com o SWARA: das dimensões (índice do trabalho) e das fontes (índice do programa).
        Schema::create('pesos_qualidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_qualidade_id')->constrained('periodos_qualidade')->cascadeOnDelete();
            $table->enum('grupo', ['dimensao', 'fonte']);
            $table->string('chave', 40);
            $table->unsignedTinyInteger('ordem');
            $table->decimal('s_j', 6, 4);
            $table->decimal('peso', 8, 6);
            $table->foreignId('definido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['periodo_qualidade_id', 'grupo', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesos_qualidade');
        Schema::dropIfExists('periodos_qualidade');
    }
};
