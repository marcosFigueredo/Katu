<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dateTime('ata_enviada_em')->nullable()->after('enviada_banca_em');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dropColumn('ata_enviada_em');
        });
    }
};
