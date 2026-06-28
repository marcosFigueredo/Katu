<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM('qualificacao','defesa','qualificacao_confidencial','defesa_confidencial') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM('qualificacao','defesa') NOT NULL");
    }
};
