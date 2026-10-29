<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->string('homepage_trabalhos')->nullable()->after('site');
        });

        Schema::table('instituicoes', function (Blueprint $table) {
            $table->string('pais', 100)->default('Brasil')->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', fn (Blueprint $table) => $table->dropColumn('pais'));
        Schema::table('programas', fn (Blueprint $table) => $table->dropColumn('homepage_trabalhos'));
    }
};
