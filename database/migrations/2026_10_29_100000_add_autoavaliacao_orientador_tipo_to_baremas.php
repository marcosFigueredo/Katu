<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TIPOS = ['qualificacao', 'defesa', 'qualificacao_confidencial', 'defesa_confidencial'];

    public function up(): void
    {
        $this->tipos([...self::TIPOS, 'autoavaliacao_orientador']);
    }

    public function down(): void
    {
        DB::table('baremas')->where('tipo', 'autoavaliacao_orientador')->delete();

        $this->tipos(self::TIPOS);
    }

    private function tipos(array $tipos): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('baremas', fn (Blueprint $table) => $table->enum('tipo', $tipos)->change());

            return;
        }

        $lista = implode(',', array_map(fn ($t) => "'{$t}'", $tipos));
        DB::statement("ALTER TABLE baremas MODIFY COLUMN tipo ENUM({$lista}) NOT NULL");
    }
};
