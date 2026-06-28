<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_analise_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_analise_id')
                ->constrained('documento_analises')
                ->cascadeOnDelete();

            $table->foreignId('documento_checklist_item_id')
                ->nullable()
                ->constrained('documento_checklist_itens')
                ->nullOnDelete();

            $table->string('nome_item');

            $table->enum('grupo', [
                'tecnico',
                'capa',
                'pre_textual',
                'textual',
                'pos_textual',
                'metadados',
                'imagens',
            ]);

            $table->enum('status', [
                'conforme',
                'atencao',
                'pendente',
                'nao_aplicavel',
            ])->default('pendente');

            $table->boolean('obrigatorio')->default(true);

            $table->boolean('impeditivo')->default(false);

            $table->text('evidencia')->nullable();

            $table->unsignedSmallInteger('pagina_detectada')->nullable();

            $table->json('termos_encontrados')->nullable();

            $table->text('mensagem')->nullable();

            $table->timestamps();

            $table->index([
                'documento_analise_id',
                'grupo',
                'status',
            ], 'doc_analise_itens_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_analise_itens');
    }
};
