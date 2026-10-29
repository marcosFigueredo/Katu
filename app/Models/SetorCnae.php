<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Setor de aplicação (CNAE 2.0, divisão), único para o sistema. */
class SetorCnae extends Model
{
    protected $table = 'setores_cnae';

    protected $fillable = ['codigo', 'nome', 'secao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
