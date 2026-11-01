<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Portaria de designação do coordenador do curso, impressa no certificado de participação na banca. */
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->string('portaria_coordenador', 500)->nullable()->after('vice_coordenador_id');
        });
    }

    public function down(): void
    {
        Schema::table('programas', fn (Blueprint $table) => $table->dropColumn('portaria_coordenador'));
    }
};
