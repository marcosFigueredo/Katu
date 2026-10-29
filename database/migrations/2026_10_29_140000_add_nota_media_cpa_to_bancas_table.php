<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            // Média com todas as avaliações (inclusive confidenciais): só para a CPA.
            $table->decimal('nota_media_cpa', 5, 2)->nullable()->after('nota_media');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', fn (Blueprint $table) => $table->dropColumn('nota_media_cpa'));
    }
};
