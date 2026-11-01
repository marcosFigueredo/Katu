<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Plágio identificado pela banca no formulário sigiloso; a unanimidade reprova a banca. */
    public function up(): void
    {
        Schema::table('banca_avaliacoes', function (Blueprint $table) {
            $table->boolean('plagio_identificado')->default(false)->after('observacoes_gerais');
            $table->text('plagio_indicacao')->nullable()->after('plagio_identificado');
        });

        Schema::table('bancas', function (Blueprint $table) {
            $table->boolean('reprovada_por_plagio')->default(false)->after('resultado');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', fn (Blueprint $table) => $table->dropColumn('reprovada_por_plagio'));
        Schema::table('banca_avaliacoes', fn (Blueprint $table) => $table->dropColumn(['plagio_identificado', 'plagio_indicacao']));
    }
};
