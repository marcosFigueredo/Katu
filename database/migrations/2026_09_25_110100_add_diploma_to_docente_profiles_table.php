<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docente_profiles', function (Blueprint $table) {
            $table->string('diploma_doutorado_path')->nullable()->after('instituicao_sigla');
            $table->string('diploma_doutorado_nome')->nullable()->after('diploma_doutorado_path');
            $table->dateTime('diploma_doutorado_enviado_em')->nullable()->after('diploma_doutorado_nome');
            $table->boolean('diploma_doutorado_verificado')->default(false)->after('diploma_doutorado_enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('docente_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'diploma_doutorado_path',
                'diploma_doutorado_nome',
                'diploma_doutorado_enviado_em',
                'diploma_doutorado_verificado',
            ]);
        });
    }
};
