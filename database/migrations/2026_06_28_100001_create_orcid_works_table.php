<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcid_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_profile_id')
                ->constrained('docente_profiles')
                ->cascadeOnDelete();
            $table->string('orcid_id', 19);
            $table->text('titulo');
            $table->smallInteger('ano')->nullable();
            $table->string('tipo', 100)->nullable();
            $table->string('doi', 500)->nullable();
            $table->string('journal', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('fonte', 50)->default('orcid');
            $table->timestamp('consultado_em');
            $table->timestamps();

            $table->index('docente_profile_id');
            $table->index('orcid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcid_works');
    }
};
