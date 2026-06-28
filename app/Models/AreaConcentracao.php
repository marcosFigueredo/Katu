<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaConcentracao extends Model
{
    protected $table = 'areas_concentracao';

    protected $fillable = [
        'programa_id',
        'nome',
        'objetivo',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function linhasPesquisa(): HasMany
    {
        return $this->hasMany(LinhaPesquisa::class);
    }
}