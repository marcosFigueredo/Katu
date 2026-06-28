<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Como ainda estamos em desenvolvimento e não cadastramos docentes/discentes reais,
         * vamos recriar as tabelas de profiles e vínculos corretamente.
         */

        Schema::dropIfExists('docente_programa_vinculos');
        Schema::dropIfExists('discente_programa_vinculos');

        Schema::dropIfExists('docente_profiles');
        Schema::dropIfExists('discente_profiles');

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

        Schema::create('docente_programa_vinculos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->foreignId('docente_profile_id')
                ->constrained('docente_profiles')
                ->cascadeOnDelete();

            $table->enum('tipo_vinculo', [
                'interno',
                'externo',
            ]);

            $table->enum('categoria_docente', [
                'permanente',
                'colaborador',
                'visitante',
            ])->nullable();

            $table->boolean('pode_orientar')->default(false);
            $table->boolean('pode_avaliar')->default(true);

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'programa_id',
                'docente_profile_id',
            ], 'docente_programa_unique');
        });

        Schema::create('discente_programa_vinculos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->foreignId('discente_profile_id')
                ->constrained('discente_profiles')
                ->cascadeOnDelete();

            $table->foreignId('linha_pesquisa_id')
                ->nullable()
                ->constrained('linhas_pesquisa')
                ->nullOnDelete();

            $table->string('matricula')->nullable();

            $table->enum('nivel', [
                'mestrado',
                'doutorado',
            ]);

            $table->enum('status', [
                'ativo',
                'concluido',
                'desligado',
                'trancado',
                'egresso',
            ])->default('ativo');

            $table->date('data_ingresso')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'programa_id',
                'discente_profile_id',
            ], 'discente_programa_unique');

            $table->unique([
                'programa_id',
                'matricula',
            ], 'discente_matricula_programa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_programa_vinculos');
        Schema::dropIfExists('discente_programa_vinculos');

        Schema::dropIfExists('docente_profiles');
        Schema::dropIfExists('discente_profiles');
    }
};