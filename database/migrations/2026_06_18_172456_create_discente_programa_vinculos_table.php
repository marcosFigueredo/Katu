<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discente_programa_vinculos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->foreignId('discente_profile_id')
                ->constrained('discente_profiles')
                ->cascadeOnDelete();

            $table->foreignId('linha_pesquisa_id')
                ->nullable()
                ->constrained('linhas_pesquisa')
                ->nullOnDelete();

            $table->string('matricula')->nullable();

            $table->enum('nivel', [
                'mestrado',
                'doutorado',
            ]);

            $table->enum('status', [
                'ativo',
                'concluido',
                'desligado',
                'trancado',
                'egresso',
            ])->default('ativo');

            $table->date('data_ingresso')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'programa_id',
                'discente_profile_id',
            ], 'discente_programa_unique');

            $table->unique([
                'programa_id',
                'matricula',
            ], 'discente_matricula_programa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discente_programa_vinculos');
    }
};