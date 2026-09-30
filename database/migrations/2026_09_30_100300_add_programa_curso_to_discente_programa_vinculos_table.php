<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PADROES = [
        'mestrado' => ['prazos' => [18, 24], 'regra' => [2, 5, 1, 1]],
        'doutorado' => ['prazos' => [30, 48], 'regra' => [5, 5, 2, 2]],
    ];

    public function up(): void
    {
        Schema::table('discente_programa_vinculos', function (Blueprint $table) {
            $table->foreignId('programa_curso_id')->nullable()->after('programa_id')
                ->constrained('programa_cursos')->nullOnDelete();
        });

        foreach (DB::table('programas')->get() as $programa) {
            $niveis = DB::table('discente_programa_vinculos')
                ->where('programa_id', $programa->id)
                ->distinct()
                ->pluck('nivel')
                ->all();
            if ($programa->oferta_mestrado) {
                $niveis[] = 'mestrado';
            }
            if ($programa->oferta_doutorado) {
                $niveis[] = 'doutorado';
            }

            foreach (array_unique($niveis) as $nivel) {
                if (! isset(self::PADROES[$nivel])) {
                    continue;
                }
                [$pq, $pd] = self::PADROES[$nivel]['prazos'];
                $cursoId = DB::table('programa_cursos')->insertGetId([
                    'programa_id' => $programa->id,
                    'nivel' => $nivel,
                    'modalidade' => 'academico',
                    'prazo_qualificacao_meses' => $pq,
                    'prazo_defesa_meses' => $pd,
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                [$min, $max, $int, $ext] = self::PADROES[$nivel]['regra'];
                foreach (['qualificacao', 'defesa'] as $etapa) {
                    DB::table('curso_regras_banca')->insert([
                        'programa_curso_id' => $cursoId,
                        'etapa' => $etapa,
                        'min_avaliadores' => $min,
                        'max_avaliadores' => $max,
                        'min_internos' => $int,
                        'min_externos_instituicao' => $ext,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('discente_programa_vinculos')
                    ->where('programa_id', $programa->id)
                    ->where('nivel', $nivel)
                    ->update(['programa_curso_id' => $cursoId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('discente_programa_vinculos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programa_curso_id');
        });
    }
};
