<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_programa_vinculos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->foreignId('docente_profile_id')
                ->constrained('docente_profiles')
                ->cascadeOnDelete();

            $table->enum('tipo_vinculo', [
                'interno',
                'externo',
            ]);

            $table->enum('categoria_docente', [
                'permanente',
                'colaborador',
                'visitante',
            ])->nullable();

            $table->boolean('pode_orientar')->default(false);
            $table->boolean('pode_avaliar')->default(true);

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'programa_id',
                'docente_profile_id',
            ], 'docente_programa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_programa_vinculos');
    }
};