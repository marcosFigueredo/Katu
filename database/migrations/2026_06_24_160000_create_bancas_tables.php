<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabalho_academico_id')
                ->unique()
                ->constrained('trabalhos_academicos')
                ->cascadeOnDelete();

            $table->enum('tipo', ['qualificacao', 'defesa'])
                ->default('qualificacao');

            $table->enum('status', ['cadastrada', 'em_avaliacao', 'finalizada', 'cancelada'])
                ->default('cadastrada');

            $table->dateTime('data_hora')->nullable();
            $table->string('local')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('libera_envio_trabalho')->default(true);

            $table->timestamps();
        });

        Schema::create('banca_membros', function (Blueprint $table) {
            $table->id();

            $table->foreignId('banca_id')
                ->constrained('bancas')
                ->cascadeOnDelete();

            $table->foreignId('docente_programa_vinculo_id')
                ->constrained('docente_programa_vinculos')
                ->cascadeOnDelete();

            $table->enum('papel', ['avaliador'])->default('avaliador');
            $table->unsignedTinyInteger('ordem')->default(1);
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'banca_id',
                'docente_programa_vinculo_id',
            ], 'banca_membros_banca_docente_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banca_membros');
        Schema::dropIfExists('bancas');
    }
};
