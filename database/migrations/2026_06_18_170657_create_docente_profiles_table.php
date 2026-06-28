<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('titulacao')->nullable();
            $table->string('area_formacao')->nullable();

            $table->string('lattes_url')->nullable();
            $table->string('orcid_url')->nullable();

            $table->string('instituicao_origem')->nullable();
            $table->string('telefone')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_profiles');
    }
};