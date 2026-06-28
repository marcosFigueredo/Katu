<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('docente_profiles', 'telefone_whatsapp')) {
            Schema::table('docente_profiles', function (Blueprint $table) {
                $table->boolean('telefone_whatsapp')
                    ->default(false)
                    ->after('telefone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('docente_profiles', 'telefone_whatsapp')) {
            Schema::table('docente_profiles', function (Blueprint $table) {
                $table->dropColumn('telefone_whatsapp');
            });
        }
    }
};
