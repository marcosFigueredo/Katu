<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barema_secoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('barema_id')
                ->constrained('baremas')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('peso', 5, 2)->default(1.00);
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index(['barema_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barema_secoes');
    }
};
