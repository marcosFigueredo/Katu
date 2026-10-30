<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dimensão da pergunta no momento da nota ("congelada"): reclassificar a pergunta depois não muda o histórico. */
    public function up(): void
    {
        Schema::table('banca_avaliacao_notas', function (Blueprint $table) {
            $table->foreignId('dimensao_qualidade_id')->nullable()->after('barema_item_id')
                ->constrained('dimensoes_qualidade', indexName: 'banca_aval_notas_dimensao_fk')->nullOnDelete();
        });

        Schema::table('autoavaliacao_orientador_notas', function (Blueprint $table) {
            $table->foreignId('dimensao_qualidade_id')->nullable()->after('barema_item_id')
                ->constrained('dimensoes_qualidade', indexName: 'autoaval_notas_dimensao_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('autoavaliacao_orientador_notas', function (Blueprint $table) {
            $table->dropForeign('autoaval_notas_dimensao_fk');
            $table->dropColumn('dimensao_qualidade_id');
        });

        Schema::table('banca_avaliacao_notas', function (Blueprint $table) {
            $table->dropForeign('banca_aval_notas_dimensao_fk');
            $table->dropColumn('dimensao_qualidade_id');
        });
    }
};
