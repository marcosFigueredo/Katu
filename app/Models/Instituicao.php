<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }

    public function logoUrl(): string
    {
        if ($this->logo_path && str_starts_with($this->logo_path, 'logos/')) {
            return route('instituicoes.logo', $this);
        }

        return asset($this->logo_path ?: 'images/logoUneb.png');
    }
}
