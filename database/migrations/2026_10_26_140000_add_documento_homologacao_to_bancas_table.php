<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->foreignId('documento_homologacao_id')->nullable()->after('homologacao_status')
                ->constrained('trabalho_documentos', indexName: 'bancas_doc_homologacao_fk')->nullOnDelete();
            $table->unsignedInteger('homologacao_tentativa')->default(1)->after('documento_homologacao_id');
        });
    }

    public function down(): void
    {
        Schema::table('bancas', function (Blueprint $table) {
            $table->dropForeign('bancas_doc_homologacao_fk');
            $table->dropIndex('bancas_doc_homologacao_fk');
            $table->dropColumn(['documento_homologacao_id', 'homologacao_tentativa']);
        });
    }
};
