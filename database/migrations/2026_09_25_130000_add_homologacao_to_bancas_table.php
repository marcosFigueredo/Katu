<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->string('homologacao_status', 30)->default('homologada')->after('status');
            $table->text('homologacao_observacoes')->nullable()->after('observacoes');
            $table->foreignId('homologada_por')->nullable()->after('homologacao_observacoes')->constrained('users')->nullOnDelete();
            $table->dateTime('homologada_em')->nullable()->after('homologada_por');
            $table->foreignId('devolvida_por')->nullable()->after('homologada_em')->constrained('users')->nullOnDelete();
            $table->dateTime('devolvida_em')->nullable()->after('devolvida_por');
            $table->index(['homologacao_status', 'tipo'], 'bancas_homologacao_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dropIndex('bancas_homologacao_lookup_idx');
            $table->dropForeign(['homologada_por']);
            $table->dropForeign(['devolvida_por']);
            $table->dropColumn([
                'homologacao_status',
                'homologacao_observacoes',
                'homologada_por',
                'homologada_em',
                'devolvida_por',
                'devolvida_em',
            ]);
        });
    }
};
