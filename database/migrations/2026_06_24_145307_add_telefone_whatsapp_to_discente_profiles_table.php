<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('discente_profiles', 'telefone_whatsapp')) {
            Schema::table('discente_profiles', function (Blueprint $table) {
                $table->boolean('telefone_whatsapp')
                    ->default(false)
                    ->after('telefone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('discente_profiles', 'telefone_whatsapp')) {
            Schema::table('discente_profiles', function (Blueprint $table) {
                $table->dropColumn('telefone_whatsapp');
            });
        }
    }
};