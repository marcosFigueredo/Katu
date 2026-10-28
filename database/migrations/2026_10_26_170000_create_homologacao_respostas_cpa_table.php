<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homologacao_respostas_cpa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')
                ->constrained('bancas', indexName: 'homol_resp_cpa_banca_fk')
                ->cascadeOnDelete();
            $table->foreignId('criterio_cpa_id')
                ->constrained('criterios_cpa', indexName: 'homol_resp_cpa_criterio_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('tentativa')->default(1);
            $table->text('pergunta');
            $table->boolean('resposta')->nullable();
            $table->foreignId('respondido_por')
                ->nullable()
                ->constrained('users', indexName: 'homol_resp_cpa_user_fk')
                ->nullOnDelete();
            $table->dateTime('respondido_em')->nullable();
            $table->timestamps();

            $table->unique(['banca_id', 'criterio_cpa_id', 'tentativa'], 'homol_resp_cpa_unique');
        });

        Schema::table('bancas', function (Blueprint $table) {
            if (! Schema::hasColumn('bancas', 'enviada_banca_em')) {
                $table->dateTime('enviada_banca_em')->nullable()->after('homologada_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            if (Schema::hasColumn('bancas', 'enviada_banca_em')) {
                $table->dropColumn('enviada_banca_em');
            }
        });

        Schema::dropIfExists('homologacao_respostas_cpa');
    }
};

