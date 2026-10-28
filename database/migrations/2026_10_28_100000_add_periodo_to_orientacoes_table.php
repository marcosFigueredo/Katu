<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientacoes', function (Blueprint $table) {
            $table->date('inicio_em')->nullable()->after('ativo');
            $table->date('fim_em')->nullable()->after('inicio_em');
            $table->string('processo_sei', 50)->nullable()->after('fim_em');
            $table->text('justificativa')->nullable()->after('processo_sei');
            $table->foreignId('registrado_por')->nullable()->after('justificativa')
                ->constrained('users', indexName: 'orientacoes_registrado_por_fk')
                ->nullOnDelete();
        });

        // Dados de teste: quem já existe começa na data de criação; encerrados terminam na última atualização.
        DB::table('orientacoes')->update(['inicio_em' => DB::raw('DATE(COALESCE(created_at, CURRENT_TIMESTAMP))')]);
        DB::table('orientacoes')->where('ativo', false)->update(['fim_em' => DB::raw('DATE(COALESCE(updated_at, CURRENT_TIMESTAMP))')]);

        Schema::table('orientacoes', function (Blueprint $table) {
            $table->date('inicio_em')->nullable(false)->change();

            // O índice simples nasce antes de derrubar o único: a FK de trabalho_academico_id precisa de um índice (erro 1553).
            $table->index(['trabalho_academico_id', 'docente_programa_vinculo_id'], 'orientacoes_trab_doc_idx');
        });

        Schema::table('orientacoes', function (Blueprint $table) {
            $table->dropUnique('orientacoes_trab_doc_unique');
        });
    }

    public function down(): void
    {
        // Volta a uma linha por docente e trabalho: os períodos encerrados somem.
        DB::table('orientacoes')->where('ativo', false)->delete();

        Schema::table('orientacoes', function (Blueprint $table) {
            $table->unique(['trabalho_academico_id', 'docente_programa_vinculo_id'], 'orientacoes_trab_doc_unique');
        });

        Schema::table('orientacoes', function (Blueprint $table) {
            $table->dropIndex('orientacoes_trab_doc_idx');
            $table->dropForeign('orientacoes_registrado_por_fk');
            $table->dropColumn(['inicio_em', 'fim_em', 'processo_sei', 'justificativa', 'registrado_por']);
        });
    }
};
