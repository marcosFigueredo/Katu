<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancaIndicador extends Model
{
    protected $table = 'banca_indicadores';

    protected $fillable = [
        'banca_id',
        'b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7', 'b8',
        'indice_banca',
        'calculado_em',
    ];

    protected function casts(): array
    {
        return [
            'b1' => 'boolean', 'b2' => 'boolean', 'b3' => 'boolean', 'b4' => 'boolean',
            'b5' => 'boolean', 'b6' => 'boolean', 'b7' => 'boolean', 'b8' => 'boolean',
            'indice_banca'  => 'float',
            'calculado_em'  => 'datetime',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }
}
