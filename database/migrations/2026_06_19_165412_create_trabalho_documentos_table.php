<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabalho_documentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabalho_academico_id')
                ->constrained('trabalhos_academicos')
                ->cascadeOnDelete();

            $table->foreignId('enviado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('tipo_documento', [
                'qualificacao',
                'defesa',
                'versao_corrigida',
                'versao_final',
            ]);

            $table->unsignedSmallInteger('versao')->default(1);

            $table->string('arquivo_pdf_path');
            $table->string('arquivo_pdf_nome_original');
            $table->string('arquivo_pdf_mime')->nullable();
            $table->unsignedBigInteger('arquivo_pdf_tamanho')->nullable();
            $table->string('arquivo_pdf_hash_sha256')->nullable();

            $table->unsignedInteger('paginas_total')->nullable();

            $table->enum('status_analise', [
                'aguardando',
                'processando',
                'concluida',
                'falhou',
            ])->default('aguardando');

            $table->timestamp('enviado_em')->nullable();

            $table->text('observacao')->nullable();

            $table->timestamps();

            $table->index([
                'trabalho_academico_id',
                'tipo_documento',
                'versao',
            ], 'trab_docs_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_documentos');
    }
};
