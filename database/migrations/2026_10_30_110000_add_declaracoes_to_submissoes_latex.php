<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalho_submissoes_latex', function (Blueprint $table) {
            $table->json('declaracoes')->nullable()->after('tipo_documento');
            $table->timestamp('declarado_em')->nullable()->after('declaracoes');
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_submissoes_latex', fn (Blueprint $table) => $table->dropColumn(['declaracoes', 'declarado_em']));
    }
};
