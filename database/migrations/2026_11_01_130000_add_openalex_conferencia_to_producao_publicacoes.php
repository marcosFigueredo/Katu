<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conferência informativa no OpenAlex: não encontrar não invalida a produção nem muda a nota.
        Schema::table('producao_publicacoes', function (Blueprint $table) {
            $table->enum('openalex_situacao', ['encontrada', 'nao_encontrada', 'falha', 'sem_conferencia'])->nullable()->after('openalex_id');
            $table->unsignedInteger('openalex_citacoes')->nullable()->after('openalex_situacao');
            $table->timestamp('openalex_verificada_em')->nullable()->after('openalex_citacoes');
        });
    }

    public function down(): void
    {
        Schema::table('producao_publicacoes', function (Blueprint $table) {
            $table->dropColumn(['openalex_situacao', 'openalex_citacoes', 'openalex_verificada_em']);
        });
    }
};
