<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docente_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('docente_profiles', 'telefone_whatsapp')) {
                $table->boolean('telefone_whatsapp')->default(false)->after('telefone');
            }
        });

        Schema::table('discente_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('discente_profiles', 'telefone_whatsapp')) {
                $table->boolean('telefone_whatsapp')->default(false)->after('telefone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('docente_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('docente_profiles', 'telefone_whatsapp')) {
                $table->dropColumn('telefone_whatsapp');
            }
        });

        Schema::table('discente_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('discente_profiles', 'telefone_whatsapp')) {
                $table->dropColumn('telefone_whatsapp');
            }
        });
    }
};