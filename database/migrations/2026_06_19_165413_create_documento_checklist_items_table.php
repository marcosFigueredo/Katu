<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_checklist_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_checklist_id')
                ->constrained('documento_checklists')
                ->cascadeOnDelete();

            $table->enum('grupo', [
                'tecnico',
                'capa',
                'pre_textual',
                'textual',
                'pos_textual',
                'metadados',
                'imagens',
            ]);

            $table->string('nome');

            $table->text('descricao')->nullable();

            $table->boolean('obrigatorio')->default(true);

            $table->boolean('impeditivo')->default(false);

            $table->enum('status_se_ausente', [
                'pendente',
                'atencao',
                'nao_aplicavel',
            ])->default('pendente');

            $table->enum('tipo_regra', [
                'texto_exato',
                'texto_flexivel',
                'classe_latex',
                'pdf_pesquisavel',
                'imagem_dpi',
                'metadado_pdf',
            ])->default('texto_flexivel');

            $table->json('termos_busca')->nullable();

            $table->unsignedSmallInteger('ordem_esperada')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index([
                'documento_checklist_id',
                'grupo',
                'ativo',
            ], 'doc_chk_itens_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_checklist_itens');
    }
};
