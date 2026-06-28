<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swara_sessoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barema_id')->constrained('baremas')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('aprovado_por')->nullable();
            $table->timestamp('aprovado_em')->nullable();
            $table->timestamp('aplicado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('swara_julgamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swara_sessao_id')->constrained('swara_sessoes')->cascadeOnDelete();
            $table->foreignId('barema_item_id')->constrained('barema_itens')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->float('s_j')->default(0);
            $table->float('k_j')->default(1);
            $table->float('q_j')->default(1);
            $table->float('w_j')->default(0);
            $table->timestamps();

            $table->unique(['swara_sessao_id', 'barema_item_id']);
            $table->unique(['swara_sessao_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swara_julgamentos');
        Schema::dropIfExists('swara_sessoes');
    }
};
