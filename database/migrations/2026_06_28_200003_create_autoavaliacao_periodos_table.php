<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autoavaliacao_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('semestre')->nullable();
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->enum('status', ['em_andamento', 'concluido'])->default('em_andamento');
            $table->timestamps();
        });

        Schema::create('autoavaliacao_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autoavaliacao_periodo_id')
                ->constrained('autoavaliacao_periodos')
                ->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nome');
            $table->float('valor_numerico')->nullable();
            $table->text('valor_texto')->nullable();
            $table->json('detalhes_json')->nullable();
            $table->timestamp('calculado_em')->nullable();
            $table->timestamps();

            $table->unique(['autoavaliacao_periodo_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoavaliacao_indicadores');
        Schema::dropIfExists('autoavaliacao_periodos');
    }
};
