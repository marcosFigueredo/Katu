<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('cadastro_status_novo', 40)->nullable()->after('cadastro_status');
            });

            DB::table('users')->update([
                'cadastro_status_novo' => DB::raw('cadastro_status'),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('cadastro_status');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('cadastro_status_novo', 'cadastro_status');
            });

            DB::statement("UPDATE users SET cadastro_status = 'pendente' WHERE cadastro_status IS NULL");

            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN cadastro_status ENUM('pendente','completo','aguardando_validacao','devolvido') NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('cadastro_status_antigo', 20)->nullable()->after('cadastro_status');
            });

            DB::table('users')->update([
                'cadastro_status_antigo' => DB::raw("CASE WHEN cadastro_status IN ('completo') THEN 'completo' ELSE 'pendente' END"),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('cadastro_status');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('cadastro_status_antigo', 'cadastro_status');
            });

            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN cadastro_status ENUM('pendente','completo') NOT NULL DEFAULT 'pendente'");
    }
};
