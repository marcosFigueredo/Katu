<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Produção associada à dissertação (dimensão 4), informada pelo estudante; o curso define o mínimo de publicações. */
    public function up(): void
    {
        Schema::create('producao_publicacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->constrained('trabalhos_academicos')->cascadeOnDelete();
            $table->enum('tipo', ['artigo', 'livro', 'capitulo', 'anais']);
            $table->string('canal', 30)->nullable();
            $table->string('titulo', 500);
            $table->unsignedSmallInteger('ano');
            $table->string('veiculo', 300);
            $table->string('doi', 255)->nullable();
            $table->string('issn', 20)->nullable();
            $table->string('openalex_id', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('producao_extensoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->constrained('trabalhos_academicos')->cascadeOnDelete();
            $table->enum('tipo', ['curso', 'oficina', 'evento', 'prestacao_servico', 'projeto']);
            $table->string('titulo', 500);
            $table->unsignedInteger('publico');
            $table->string('parceiro', 300);
            $table->unsignedInteger('carga_horaria');
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->string('registro_proex', 60)->nullable();
            $table->string('produto_entregue', 500)->nullable();
            $table->string('link', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('producao_popularizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')->constrained('trabalhos_academicos')->cascadeOnDelete();
            $table->enum('tipo', ['palestra', 'feira', 'imprensa', 'video', 'podcast', 'rede_social', 'material_didatico']);
            $table->string('titulo', 500);
            $table->string('veiculo', 300);
            $table->unsignedInteger('alcance');
            $table->date('data');
            $table->string('link', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('trabalhos_academicos', function (Blueprint $table) {
            $table->timestamp('producao_confirmada_em')->nullable();
        });

        Schema::table('programa_cursos', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_publicacoes_defesa')->default(0)->after('prazo_defesa_meses');
        });
    }

    public function down(): void
    {
        Schema::table('programa_cursos', fn (Blueprint $table) => $table->dropColumn('min_publicacoes_defesa'));
        Schema::table('trabalhos_academicos', fn (Blueprint $table) => $table->dropColumn('producao_confirmada_em'));
        Schema::dropIfExists('producao_popularizacoes');
        Schema::dropIfExists('producao_extensoes');
        Schema::dropIfExists('producao_publicacoes');
    }
};
