<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimensoes_qualidade', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nome');
            $table->unsignedTinyInteger('ordem');
            $table->text('descricao')->nullable();
            $table->timestamps();
        });

        // As 8 dimensões são fixas: entram já na migration (o seeder só repete a carga).
        $agora = now();
        DB::table('dimensoes_qualidade')->insert(array_map(
            fn ($d) => $d + ['created_at' => $agora, 'updated_at' => $agora],
            \App\Models\DimensaoQualidade::PADRAO,
        ));

        Schema::table('barema_itens', function (Blueprint $table) {
            $table->foreignId('dimensao_qualidade_id')->nullable()->after('barema_secao_id')
                ->constrained('dimensoes_qualidade', indexName: 'barema_itens_dimensao_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('barema_itens', function (Blueprint $table) {
            $table->dropForeign('barema_itens_dimensao_fk');
            $table->dropColumn('dimensao_qualidade_id');
        });

        Schema::dropIfExists('dimensoes_qualidade');
    }
};
