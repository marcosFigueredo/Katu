<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('cadastro_status', [
                'pendente',
                'completo',
            ])
                ->default('pendente')
                ->after('ativo');
        });

        Schema::table('docente_profiles', function (Blueprint $table) {
            $table->string('instituicao_sigla', 50)
                ->nullable()
                ->after('instituicao_origem');

            $table->boolean('lattes_verificado')
                ->default(false)
                ->after('lattes_url');

            $table->boolean('orcid_verificado')
                ->default(false)
                ->after('orcid_url');
        });

        Schema::table('discente_profiles', function (Blueprint $table) {
            $table->boolean('lattes_verificado')
                ->default(false)
                ->after('lattes_url');

            $table->boolean('orcid_verificado')
                ->default(false)
                ->after('orcid_url');
        });
    }

    public function down(): void
    {
        Schema::table('discente_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'lattes_verificado',
                'orcid_verificado',
            ]);
        });

        Schema::table('docente_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'instituicao_sigla',
                'lattes_verificado',
                'orcid_verificado',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cadastro_status');
        });
    }
};