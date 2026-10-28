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
            // No SQLite (testes) o enum é um CHECK: ->change() reconstrói a tabela com os tipos novos.
            Schema::table('baremas', function (Blueprint $table) {
                $table->enum('tipo', ['qualificacao', 'defesa', 'qualificacao_confidencial', 'defesa_confidencial'])->change();
            });

            return;
        }

        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM('qualificacao','defesa','qualificacao_confidencial','defesa_confidencial') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('baremas', function (Blueprint $table) {
                $table->enum('tipo', ['qualificacao', 'defesa'])->change();
            });

            return;
        }

        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM('qualificacao','defesa') NOT NULL");
    }
};
