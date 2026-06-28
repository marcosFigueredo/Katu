<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('instituicoes', 'departamento')) {
            Schema::table('instituicoes', function (Blueprint $table) {
                $table->string('departamento')->nullable()->after('sigla');
            });
        }

        DB::table('instituicoes')
            ->whereNull('logo_path')
            ->update(['logo_path' => 'images/logoUneb.png']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('instituicoes', 'departamento')) {
            Schema::table('instituicoes', function (Blueprint $table) {
                $table->dropColumn('departamento');
            });
        }
    }
};
