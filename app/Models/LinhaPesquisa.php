<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinhaPesquisa extends Model
{
    protected $table = 'linhas_pesquisa';

    protected $fillable = [
        'area_concentracao_id',
        'nome',
        'objetivo',
        'ods',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ods' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function areaConcentracao(): BelongsTo
    {
        return $this->belongsTo(AreaConcentracao::class);
    }
}