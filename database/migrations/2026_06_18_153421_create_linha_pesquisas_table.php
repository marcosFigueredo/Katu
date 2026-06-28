<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linhas_pesquisa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('area_concentracao_id')
                ->constrained('areas_concentracao')
                ->cascadeOnDelete();

            $table->string('nome');

            $table->text('objetivo')->nullable();

            $table->json('ods')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linhas_pesquisa');
    }
};