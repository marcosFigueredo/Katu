<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Avaliação do orientando pelo orientador, só na defesa; privada à CPA (dimensão 5). */
    public function up(): void
    {
        Schema::create('avaliacoes_orientando', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->unique()->constrained('bancas')->cascadeOnDelete();
            $table->foreignId('docente_programa_vinculo_id')->constrained('docente_programa_vinculos')->restrictOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamp('enviada_em');
            $table->timestamps();
        });

        Schema::create('avaliacao_orientando_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_orientando_id')->constrained('avaliacoes_orientando')->cascadeOnDelete();
            $table->string('elemento', 30);
            $table->decimal('nota', 4, 2);
            $table->foreignId('dimensao_qualidade_id')->nullable()->constrained('dimensoes_qualidade')->nullOnDelete();
            $table->timestamps();
            $table->unique(['avaliacao_orientando_id', 'elemento'], 'avaliacao_orientando_elemento_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacao_orientando_notas');
        Schema::dropIfExists('avaliacoes_orientando');
    }
};
