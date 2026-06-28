<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_usuarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programa_id')
                ->constrained('programas')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('categoria_no_programa', [
                'secretaria',
            ])->default('secretaria');

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique([
                'programa_id',
                'user_id',
                'categoria_no_programa',
            ], 'programa_usuario_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_usuarios');
    }
};