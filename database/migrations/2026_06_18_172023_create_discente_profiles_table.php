<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discente_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('formacao')->nullable();
            $table->string('instituicao_formacao')->nullable();

            $table->string('lattes_url')->nullable();
            $table->string('orcid_url')->nullable();

            $table->string('telefone')->nullable();

            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('pais')->default('Brasil');

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discente_profiles');
    }
};