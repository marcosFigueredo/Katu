<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabalho_submissoes_latex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_academico_id')
                ->constrained('trabalhos_academicos', indexName: 'subm_latex_trabalho_fk')->cascadeOnDelete();
            $table->foreignId('banca_id')->nullable()
                ->constrained('bancas', indexName: 'subm_latex_banca_fk')->nullOnDelete();
            $table->foreignId('enviado_por_id')->nullable()
                ->constrained('users', indexName: 'subm_latex_enviado_por_fk')->nullOnDelete();
            $table->string('tipo_documento', 20); // qualificacao|dissertacao|tese
            $table->string('idioma', 2)->default('pt'); // pt|en

            $table->string('zip_path');
            $table->string('zip_hash_sha256', 64);
            $table->string('tex_principal')->nullable();
            $table->string('pdf_banca_path')->nullable();
            $table->string('pdf_conferencia_path')->nullable();
            $table->string('bbl_path')->nullable();
            $table->string('motor', 20)->nullable();

            $table->string('status', 20)->default('processando'); // processando|ok|nao_ok|erro
            $table->json('elementos')->nullable();
            $table->json('conformidade')->nullable();
            $table->text('log_resumido')->nullable();
            $table->json('avisos')->nullable();
            $table->boolean('numeracao_linhas')->default(true);

            $table->string('refs_status', 20)->default('pendente'); // pendente|processando|concluida|erro
            $table->unsignedInteger('refs_total')->nullable();
            $table->unsignedInteger('refs_com_ano')->nullable();
            $table->decimal('refs_recentes_pct', 5, 2)->nullable();
            $table->boolean('refs_atualizadas')->nullable();
            $table->unsignedInteger('refs_orientador')->nullable();
            $table->timestamps();
        });

        Schema::table('trabalho_documentos', function (Blueprint $table) {
            $table->foreignId('submissao_latex_id')->nullable()->after('trabalho_academico_id')
                ->constrained('trabalho_submissoes_latex', indexName: 'trab_docs_subm_latex_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_documentos', function (Blueprint $table) {
            $table->dropForeign('trab_docs_subm_latex_fk');
            $table->dropIndex('trab_docs_subm_latex_fk');
            $table->dropColumn('submissao_latex_id');
        });

        Schema::dropIfExists('trabalho_submissoes_latex');
    }
};
