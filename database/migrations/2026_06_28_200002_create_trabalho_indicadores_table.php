<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabalho_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')
                ->unique()
                ->constrained('trabalhos_academicos')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('total_publicacoes_discente')->default(0);
            $table->unsignedSmallInteger('total_com_doi')->default(0);
            $table->unsignedSmallInteger('total_citacoes_crossref')->default(0);
            $table->boolean('coautoria_orientador')->default(false);
            $table->boolean('coautoria_internacional')->default(false);
            $table->boolean('presenca_bases_indexadas')->default(false);
            $table->json('dados_json')->nullable();
            $table->timestamp('consultado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_indicadores');
    }
};
