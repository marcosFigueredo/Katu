<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_analises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabalho_documento_id')
                ->constrained('trabalho_documentos')
                ->cascadeOnDelete();

            $table->foreignId('documento_checklist_id')
                ->nullable()
                ->constrained('documento_checklists')
                ->nullOnDelete();

            $table->enum('status_geral', [
                'conforme',
                'com_alertas',
                'com_pendencias',
                'falha',
            ])->default('com_pendencias');

            $table->unsignedTinyInteger('percentual_conformidade')->default(0);

            $table->unsignedSmallInteger('total_itens')->default(0);
            $table->unsignedSmallInteger('itens_conformes')->default(0);
            $table->unsignedSmallInteger('itens_atencao')->default(0);
            $table->unsignedSmallInteger('itens_pendentes')->default(0);
            $table->unsignedSmallInteger('itens_nao_aplicaveis')->default(0);

            $table->boolean('pdf_pesquisavel')->default(false);
            $table->boolean('pdf_protegido')->default(false);
            $table->boolean('classe_latex_detectada')->default(false);

            $table->string('classe_latex_nome')->nullable();
            $table->string('classe_latex_versao')->nullable();
            $table->string('motor_tex')->nullable();
            $table->string('codigo_certificacao')->nullable();

            $table->longText('texto_extraido')->nullable();

            $table->text('mensagem_geral')->nullable();

            $table->timestamp('analisado_em')->nullable();

            $table->timestamps();

            $table->index([
                'trabalho_documento_id',
                'status_geral',
            ], 'doc_analises_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_analises');
    }
};
