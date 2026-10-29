<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalhos_academicos', function (Blueprint $table) {
            $table->string('titulo_en', 500)->nullable()->after('titulo');
            $table->text('outras_informacoes')->nullable();
            $table->text('outras_informacoes_en')->nullable();
            $table->enum('visibilidade', ['publica', 'privada'])->default('publica');
            $table->foreignId('setor_cnae_id')->nullable()->constrained('setores_cnae', indexName: 'trabalhos_setor_cnae_fk')->nullOnDelete();
        });

        Schema::create('trabalho_area_cnpq', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->constrained('trabalhos_academicos', indexName: 'trab_area_cnpq_trabalho_fk')->cascadeOnDelete();
            $table->foreignId('area_cnpq_id')->constrained('areas_cnpq', indexName: 'trab_area_cnpq_area_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['trabalho_academico_id', 'area_cnpq_id'], 'trab_area_cnpq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_area_cnpq');

        Schema::table('trabalhos_academicos', function (Blueprint $table) {
            $table->dropForeign('trabalhos_setor_cnae_fk');
            $table->dropColumn(['titulo_en', 'outras_informacoes', 'outras_informacoes_en', 'visibilidade', 'setor_cnae_id']);
        });
    }
};
