<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banca_avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->constrained('bancas')->cascadeOnDelete();
            $table->foreignId('banca_membro_id')->constrained('banca_membros')->cascadeOnDelete();
            $table->foreignId('barema_id')->constrained('baremas')->restrictOnDelete();
            $table->enum('status', ['rascunho', 'enviada'])->default('rascunho');
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->text('observacoes_gerais')->nullable();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();
            $table->unique(['banca_membro_id', 'barema_id']);
        });

        Schema::create('banca_avaliacao_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_avaliacao_id')
                ->constrained('banca_avaliacoes')
                ->cascadeOnDelete();
            $table->foreignId('barema_item_id')
                ->constrained('barema_itens')
                ->restrictOnDelete();
            $table->decimal('nota', 5, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->unique(['banca_avaliacao_id', 'barema_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banca_avaliacao_notas');
        Schema::dropIfExists('banca_avaliacoes');
    }
};
