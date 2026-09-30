<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_documentos_referencia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->enum('tipo', ['normas', 'template']);

            $table->string('arquivo_path');
            $table->string('arquivo_nome_original');
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->string('hash_sha256')->nullable();

            $table->foreignId('enviado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('enviado_em')->nullable();

            $table->enum('status_indexacao', ['pendente', 'processando', 'concluido', 'falhou'])
                ->default('pendente');
            $table->timestamp('indexado_em')->nullable();
            $table->text('erro_indexacao')->nullable();

            $table->timestamps();

            $table->unique(['programa_id', 'tipo'], 'prog_doc_ref_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_documentos_referencia');
    }
};
