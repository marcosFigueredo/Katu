<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programa_templates_latex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->unique('prog_tpl_latex_programa_unique')
                ->constrained('programas', indexName: 'prog_tpl_latex_programa_fk')->cascadeOnDelete();
            $table->string('arquivo_path');
            $table->string('arquivo_nome_original');
            $table->string('hash_sha256', 64);
            $table->string('tex_principal');
            $table->string('classe_documento')->nullable();
            $table->string('motor')->nullable();
            $table->json('mapa_elementos')->nullable();
            $table->foreignId('enviado_por')
                ->constrained('users', indexName: 'prog_tpl_latex_enviado_por_fk');
            $table->timestamp('enviado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_templates_latex');
    }
};
