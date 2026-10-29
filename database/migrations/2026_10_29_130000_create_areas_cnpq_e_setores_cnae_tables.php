<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_cnpq', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            $table->string('nome');
            $table->enum('nivel', ['grande_area', 'area', 'subarea']);
            $table->foreignId('pai_id')->nullable()->constrained('areas_cnpq', indexName: 'areas_cnpq_pai_fk')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('setores_cnae', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nome');
            $table->string('secao', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setores_cnae');
        Schema::dropIfExists('areas_cnpq');
    }
};
