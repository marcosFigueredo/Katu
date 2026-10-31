<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espelhos_discente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->unique()->constrained('bancas', indexName: 'espelho_disc_banca_fk')->cascadeOnDelete();
            $table->foreignId('barema_id')->constrained('baremas', indexName: 'espelho_disc_barema_fk')->restrictOnDelete();
            $table->decimal('nota_final', 5, 2);
            $table->timestamp('enviada_em');
            $table->timestamps();
        });

        Schema::create('espelho_discente_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espelho_discente_id')->constrained('espelhos_discente', indexName: 'espelho_nota_espelho_fk')->cascadeOnDelete();
            $table->foreignId('barema_item_id')->constrained('barema_itens', indexName: 'espelho_nota_item_fk')->restrictOnDelete();
            $table->foreignId('dimensao_qualidade_id')->nullable()->constrained('dimensoes_qualidade', indexName: 'espelho_nota_dimensao_fk')->restrictOnDelete();
            $table->decimal('nota', 5, 2);
            $table->timestamps();

            $table->unique(['espelho_discente_id', 'barema_item_id'], 'espelho_nota_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espelho_discente_notas');
        Schema::dropIfExists('espelhos_discente');
    }
};
