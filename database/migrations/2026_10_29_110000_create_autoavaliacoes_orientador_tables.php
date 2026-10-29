<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autoavaliacoes_orientador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->constrained('bancas', indexName: 'autoaval_orient_banca_fk')->cascadeOnDelete();
            $table->foreignId('docente_programa_vinculo_id')->constrained('docente_programa_vinculos', indexName: 'autoaval_orient_docente_fk')->cascadeOnDelete();
            $table->foreignId('barema_id')->constrained('baremas', indexName: 'autoaval_orient_barema_fk')->restrictOnDelete();
            $table->decimal('nota_final', 5, 2);
            $table->text('observacoes')->nullable();
            $table->timestamp('enviada_em');
            $table->timestamps();

            $table->unique(['banca_id', 'docente_programa_vinculo_id'], 'autoaval_orient_banca_docente_unique');
        });

        Schema::create('autoavaliacao_orientador_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autoavaliacao_orientador_id')->constrained('autoavaliacoes_orientador', indexName: 'autoaval_nota_autoaval_fk')->cascadeOnDelete();
            $table->foreignId('barema_item_id')->constrained('barema_itens', indexName: 'autoaval_nota_item_fk')->restrictOnDelete();
            $table->decimal('nota', 5, 2);
            $table->timestamps();

            $table->unique(['autoavaliacao_orientador_id', 'barema_item_id'], 'autoaval_nota_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoavaliacao_orientador_notas');
        Schema::dropIfExists('autoavaliacoes_orientador');
    }
};
