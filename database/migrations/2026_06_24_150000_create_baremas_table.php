<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baremas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->enum('tipo', ['qualificacao', 'defesa']);
            $table->enum('nivel', ['mestrado', 'doutorado', 'ambos'])->default('ambos');
            $table->string('versao', 20);
            $table->boolean('ativo')->default(false);
            $table->text('descricao')->nullable();

            $table->timestamps();

            $table->index(['programa_id', 'tipo', 'nivel', 'ativo'], 'baremas_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baremas');
    }
};
