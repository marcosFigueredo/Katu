<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Marcações da Secretaria em cada publicação, na homologação da defesa (categorização para análise futura). */
    public function up(): void
    {
        Schema::table('producao_publicacoes', function (Blueprint $table) {
            $table->boolean('derivada_dissertacao')->nullable();
            $table->enum('situacao', ['publicada', 'aceita', 'submetida'])->nullable();
            $table->boolean('discente_primeiro_autor')->nullable();
            $table->boolean('coautoria_orientador')->nullable();
            $table->boolean('coautoria_internacional')->nullable();
            $table->boolean('conta_minimo')->nullable();
            $table->foreignId('conferida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conferida_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('producao_publicacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conferida_por');
            $table->dropColumn(['derivada_dissertacao', 'situacao', 'discente_primeiro_autor', 'coautoria_orientador',
                'coautoria_internacional', 'conta_minimo', 'conferida_em']);
        });
    }
};
