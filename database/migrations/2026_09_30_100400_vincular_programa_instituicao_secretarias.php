<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('programas')->count() !== 1) {
            return;
        }
        $programaId = DB::table('programas')->value('id');

        if (DB::table('instituicoes')->count() === 1) {
            DB::table('programas')->where('id', $programaId)->whereNull('instituicao_id')
                ->update(['instituicao_id' => DB::table('instituicoes')->value('id')]);
        }

        $secretariasComPrograma = DB::table('programa_usuarios')
            ->where('categoria_no_programa', 'secretaria')
            ->where('ativo', true)
            ->pluck('user_id');

        DB::table('users')->where('categoria', 'sec')
            ->whereNotIn('id', $secretariasComPrograma)
            ->pluck('id')
            ->each(fn ($userId) => DB::table('programa_usuarios')->insert([
                'programa_id' => $programaId,
                'user_id' => $userId,
                'categoria_no_programa' => 'secretaria',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        // Migração de dados: não desfaz vínculos.
    }
};
