<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barema_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('barema_secao_id')
                ->constrained('barema_secoes')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('nota_maxima', 5, 2)->default(10.00);
            $table->decimal('peso', 5, 2)->default(1.00);
            $table->boolean('obrigatorio')->default(true);
            $table->string('indicador_capes', 100)->nullable();
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index(['barema_secao_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barema_itens');
    }
};
