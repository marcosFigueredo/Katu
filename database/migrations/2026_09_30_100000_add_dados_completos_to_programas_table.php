<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->foreignId('instituicao_id')->nullable()->after('id')->constrained('instituicoes')->nullOnDelete();
            $table->unsignedTinyInteger('conceito_capes')->nullable()->after('area_avaliacao');
            $table->unsignedSmallInteger('ano_inicio')->nullable()->after('conceito_capes');
            $table->foreignId('coordenador_id')->nullable()->after('ano_inicio')->constrained('users')->nullOnDelete();
            $table->foreignId('vice_coordenador_id')->nullable()->after('coordenador_id')->constrained('users')->nullOnDelete();
            $table->string('logo_path')->nullable()->after('descricao');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instituicao_id');
            $table->dropConstrainedForeignId('coordenador_id');
            $table->dropConstrainedForeignId('vice_coordenador_id');
            $table->dropColumn(['conceito_capes', 'ano_inicio', 'logo_path']);
        });
    }
};
