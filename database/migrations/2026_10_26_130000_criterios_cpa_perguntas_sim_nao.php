<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('banca_evidencias');

        // No MySQL a chave estrangeira de programa_id usa um destes índices:
        // o novo precisa existir antes de derrubar o único e o antigo.
        if (! Schema::hasIndex('criterios_cpa', 'criterios_cpa_pergunta_aplicacao_idx')) {
            Schema::table('criterios_cpa', function (Blueprint $table) {
                $table->index(['programa_id', 'etapa', 'nivel', 'ativo'], 'criterios_cpa_pergunta_aplicacao_idx');
            });
        }

        foreach (['criterios_cpa_programa_codigo_versao_unique' => 'dropUnique', 'criterios_cpa_aplicacao_idx' => 'dropIndex'] as $nome => $metodo) {
            if (Schema::hasIndex('criterios_cpa', $nome)) {
                Schema::table('criterios_cpa', fn (Blueprint $table) => $table->{$metodo}($nome));
            }
        }

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'descricao', 'tipo_evidencia', 'obrigatorio', 'versao']);
        });

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->renameColumn('titulo', 'pergunta');
        });

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->text('pergunta')->change();
            $table->string('etapa', 30)->nullable()->change();
            $table->dateTime('inativado_em')->nullable()->after('ativo');
        });

        // Perguntas que já estavam inativas ficam com a data da última alteração.
        DB::table('criterios_cpa')->where('ativo', false)->update(['inativado_em' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        DB::table('criterios_cpa')->whereNull('etapa')->update(['etapa' => 'qualificacao']);

        // A FK de programa_id precisa de um índice o tempo todo (MySQL, erro 1553):
        // o antigo é criado antes de derrubar o novo.
        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->index(['programa_id', 'etapa', 'nivel', 'ativo'], 'criterios_cpa_aplicacao_idx');
        });

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->dropIndex('criterios_cpa_pergunta_aplicacao_idx');
            $table->dropColumn('inativado_em');
        });

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->renameColumn('pergunta', 'titulo');
        });

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->string('titulo')->change();
            $table->string('etapa', 30)->nullable(false)->change();
            $table->string('codigo', 50)->default('');
            $table->text('descricao')->nullable();
            $table->string('tipo_evidencia', 30)->default('declaracao');
            $table->boolean('obrigatorio')->default(true);
            $table->string('versao', 20)->default('1.0');
        });

        $concatenar = DB::getDriverName() === 'sqlite' ? "'CPA-' || id" : "CONCAT('CPA-', id)";
        DB::table('criterios_cpa')->update(['codigo' => DB::raw($concatenar)]);

        Schema::table('criterios_cpa', function (Blueprint $table) {
            $table->unique(['programa_id', 'etapa', 'codigo', 'versao'], 'criterios_cpa_programa_codigo_versao_unique');
        });

        Schema::create('banca_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')->constrained('bancas')->cascadeOnDelete();
            $table->foreignId('criterio_cpa_id')->constrained('criterios_cpa')->cascadeOnDelete();
            $table->string('status', 30)->default('ausente');
            $table->text('valor_texto')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('arquivo_nome')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('informado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('informado_em')->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validado_em')->nullable();
            $table->timestamps();

            $table->unique(['banca_id', 'criterio_cpa_id'], 'banca_evidencia_banca_criterio_unique');
            $table->index(['banca_id', 'status']);
        });
    }
};
