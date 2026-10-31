<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cópia dos dados da pesquisa no momento do envio à homologação: o estudante pode editá-los depois sem mudar o que a banca recebeu. */
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->json('dados_pesquisa_enviados')->nullable()->after('documento_homologacao_id');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dropColumn('dados_pesquisa_enviados');
        });
    }
};
