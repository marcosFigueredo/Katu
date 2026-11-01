<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bolsas do estudante, registradas pela Secretaria a partir dos documentos oficiais (sem comprovante).
        Schema::create('bolsas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discente_programa_vinculo_id')->constrained('discente_programa_vinculos')->cascadeOnDelete();
            $table->enum('agencia', ['capes', 'cnpq', 'fapesb', 'institucional', 'outra']);
            $table->string('agencia_outra', 150)->nullable();
            $table->enum('modalidade', ['mestrado', 'doutorado', 'doutorado_sanduiche']);
            $table->string('numero_processo', 100)->nullable();
            $table->date('inicio_em');
            $table->date('fim_em')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bolsas');
    }
};
