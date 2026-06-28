<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancaAvaliacaoNota extends Model
{
    protected $table = 'banca_avaliacao_notas';

    protected $fillable = [
        'banca_avaliacao_id',
        'barema_item_id',
        'nota',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'float',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(BancaAvaliacao::class, 'banca_avaliacao_id');
    }

    public function baremaItem(): BelongsTo
    {
        return $this->belongsTo(BaremaItem::class);
    }
}
