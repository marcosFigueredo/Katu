<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table) {
            $table->id();

            $table->string('nome');
            $table->string('sigla', 50)->nullable();

            $table->string('codigo_capes', 50)->nullable();
            $table->string('area_avaliacao')->nullable();


            $table->boolean('oferta_mestrado')->default(true);
            $table->boolean('oferta_doutorado')->default(false);

            $table->string('email')->nullable();
            $table->string('site')->nullable();
            $table->string('telefone')->nullable();

            $table->text('descricao')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};