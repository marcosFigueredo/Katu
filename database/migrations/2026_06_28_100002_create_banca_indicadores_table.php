<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banca_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banca_id')
                ->unique()
                ->constrained('bancas')
                ->cascadeOnDelete();
            $table->boolean('b1')->default(false);
            $table->boolean('b2')->default(false);
            $table->boolean('b3')->default(false);
            $table->boolean('b4')->default(false);
            $table->boolean('b5')->default(false);
            $table->boolean('b6')->default(false);
            $table->boolean('b7')->default(false);
            $table->boolean('b8')->default(false);
            $table->float('indice_banca')->default(0);
            $table->timestamp('calculado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banca_indicadores');
    }
};
