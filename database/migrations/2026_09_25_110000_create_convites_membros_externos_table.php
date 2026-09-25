<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convites_membros_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome');
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 40)->default('enviado');
            $table->text('observacoes')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index(['programa_id', 'status']);
            $table->index(['email', 'programa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites_membros_externos');
    }
};
