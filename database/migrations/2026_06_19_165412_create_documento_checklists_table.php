<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_checklists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->nullable()
                ->constrained('programas')
                ->nullOnDelete();

            $table->string('nome');

            $table->enum('tipo_documento', [
                'qualificacao',
                'defesa',
                'versao_corrigida',
                'versao_final',
            ]);

            $table->enum('nivel', [
                'mestrado',
                'doutorado',
                'ambos',
            ])->default('ambos');

            $table->string('versao')->default('1.0');

            $table->boolean('ativo')->default(true);

            $table->text('descricao')->nullable();

            $table->timestamps();

            $table->index([
                'programa_id',
                'tipo_documento',
                'nivel',
                'ativo',
            ], 'doc_checklists_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_checklists');
    }
};
