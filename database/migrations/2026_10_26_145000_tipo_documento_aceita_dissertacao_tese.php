<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O formulário do discente envia dissertacao|tese, mas o enum só aceitava qualificacao|defesa|versao_*.
 * Converte as duas colunas para string(30); a validação fica na aplicação.
 */
return new class extends Migration
{
    private const ENUM_ORIGINAL = ['qualificacao', 'defesa', 'versao_corrigida', 'versao_final'];

    public function up(): void
    {
        foreach (['trabalho_documentos', 'documento_checklists'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('tipo_documento', 30)->change();
            });
        }
    }

    public function down(): void
    {
        // Linhas com valores fora do enum antigo voltam ao equivalente anterior (defesa).
        foreach (['trabalho_documentos', 'documento_checklists'] as $tabela) {
            DB::table($tabela)->whereIn('tipo_documento', ['dissertacao', 'tese'])->update(['tipo_documento' => 'defesa']);

            Schema::table($tabela, function (Blueprint $table) {
                $table->enum('tipo_documento', self::ENUM_ORIGINAL)->change();
            });
        }
    }
};
