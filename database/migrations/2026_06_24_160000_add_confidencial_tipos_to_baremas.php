<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM('qualificacao','defesa','qualificacao_confidencial','defesa_confidencial') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM('qualificacao','defesa') NOT NULL");
    }
};
