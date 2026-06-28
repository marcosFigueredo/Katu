<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwaraJulgamento extends Model
{
    protected $table = 'swara_julgamentos';

    protected $fillable = [
        'swara_sessao_id',
        'barema_item_id',
        'ordem',
        's_j',
        'k_j',
        'q_j',
        'w_j',
    ];

    protected function casts(): array
    {
        return [
            's_j' => 'float',
            'k_j' => 'float',
            'q_j' => 'float',
            'w_j' => 'float',
        ];
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(SwaraSessao::class, 'swara_sessao_id');
    }

    public function baremaItem(): BelongsTo
    {
        return $this->belongsTo(BaremaItem::class);
    }
}
