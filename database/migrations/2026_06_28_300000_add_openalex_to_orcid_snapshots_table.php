<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orcid_snapshots', function (Blueprint $table) {
            $table->unsignedSmallInteger('indice_h')->nullable()->after('total_obras_com_doi');
            $table->unsignedInteger('total_citacoes')->nullable()->after('indice_h');
            $table->unsignedSmallInteger('i10_index')->nullable()->after('total_citacoes');
            $table->unsignedInteger('works_count_openalex')->nullable()->after('i10_index');
        });
    }

    public function down(): void
    {
        Schema::table('orcid_snapshots', function (Blueprint $table) {
            $table->dropColumn(['indice_h', 'total_citacoes', 'i10_index', 'works_count_openalex']);
        });
    }
};
