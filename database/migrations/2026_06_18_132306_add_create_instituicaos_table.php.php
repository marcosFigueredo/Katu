<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituicoes', function (Blueprint $table) {
            $table->id();

            $table->string('nome');
            $table->string('sigla')->nullable();

            $table->string('cnpj')->nullable();

            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep')->nullable();

            $table->string('site')->nullable();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();

            $table->string('logo_path')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituicoes');
    }
};