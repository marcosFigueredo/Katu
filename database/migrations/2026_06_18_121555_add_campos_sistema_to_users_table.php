<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('categoria', ['sec', 'docente', 'discente'])
                ->default('discente')
                ->after('password');

            $table->boolean('primeiro_acesso')
                ->default(true)
                ->after('categoria');

            $table->boolean('ativo')
                ->default(true)
                ->after('primeiro_acesso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'categoria',
                'primeiro_acesso',
                'ativo',
            ]);
        });
    }
};