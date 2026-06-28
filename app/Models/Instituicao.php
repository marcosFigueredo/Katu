<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instituicao extends Model
{
    protected $table = 'instituicoes';

    protected $fillable = [
        'nome',
        'sigla',
        'departamento',
        'cnpj',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'site',
        'email',
        'telefone',
        'logo_path',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
