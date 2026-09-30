<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE documento_checklist_itens
            MODIFY COLUMN tipo_regra ENUM(
                'texto_exato',
                'texto_flexivel',
                'classe_latex',
                'pdf_pesquisavel',
                'imagem_dpi',
                'metadado_pdf',
                'metadados_final'
            ) NOT NULL DEFAULT 'texto_flexivel'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE documento_checklist_itens
            MODIFY COLUMN tipo_regra ENUM(
                'texto_exato',
                'texto_flexivel',
                'classe_latex',
                'pdf_pesquisavel',
                'imagem_dpi',
                'metadado_pdf'
            ) NOT NULL DEFAULT 'texto_flexivel'
        ");
    }
};
