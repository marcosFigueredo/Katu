<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalho_submissoes_latex', function (Blueprint $table) {
            // sha256 da composição da banca conferida (avaliadores, data/hora, local)
            $table->string('banca_composicao_hash', 64)->nullable()->after('banca_id');
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_submissoes_latex', function (Blueprint $table) {
            $table->dropColumn('banca_composicao_hash');
        });
    }
};
