<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcid_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_profile_id')
                ->unique()
                ->constrained('docente_profiles')
                ->cascadeOnDelete();
            $table->string('orcid_id', 19);
            $table->string('url_orcid')->nullable();
            $table->string('nome_publico')->nullable();
            $table->unsignedSmallInteger('total_obras')->default(0);
            $table->unsignedSmallInteger('total_obras_com_doi')->default(0);
            $table->json('dados_json')->nullable();
            $table->enum('status_consulta', ['pendente', 'sucesso', 'erro'])->default('pendente');
            $table->text('mensagem_erro')->nullable();
            $table->timestamp('consultado_em')->nullable();
            $table->timestamps();

            $table->index('orcid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcid_snapshots');
    }
};
