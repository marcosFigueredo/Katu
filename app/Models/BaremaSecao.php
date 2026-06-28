<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaremaSecao extends Model
{
    protected $table = 'barema_secoes';

    protected $fillable = [
        'barema_id',
        'nome',
        'descricao',
        'peso',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'peso'  => 'float',
        ];
    }

    public function barema(): BelongsTo
    {
        return $this->belongsTo(Barema::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(BaremaItem::class, 'barema_secao_id')->orderBy('ordem');
    }
}
