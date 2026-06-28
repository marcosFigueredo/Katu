<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->enum('resultado', ['aprovado', 'aprovado_modificacoes', 'reprovado'])
                ->nullable()
                ->after('status');
            $table->decimal('nota_media', 5, 2)->nullable()->after('resultado');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dropColumn(['resultado', 'nota_media']);
        });
    }
};
