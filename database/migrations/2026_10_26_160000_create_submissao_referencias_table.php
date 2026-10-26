<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissao_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submissao_id')
                ->constrained('trabalho_submissoes_latex', indexName: 'subm_refs_submissao_fk')->cascadeOnDelete();
            $table->string('chave')->nullable();
            $table->text('texto');
            $table->string('doi_tex', 500)->nullable();
            $table->unsignedSmallInteger('ano_tex')->nullable();
            $table->text('titulo_tex')->nullable();
            $table->string('status', 20); // localizada|nao_localizada|nao_verificada
            $table->string('fonte', 20)->nullable(); // openalex|crossref
            $table->string('doi_encontrado', 500)->nullable();
            $table->unsignedSmallInteger('ano_encontrado')->nullable();
            $table->boolean('divergencia_doi')->default(false);
            $table->boolean('divergencia_ano')->default(false);
            $table->boolean('do_orientador')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissao_referencias');
    }
};
