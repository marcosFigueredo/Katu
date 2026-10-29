<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoavaliacaoOrientadorNota extends Model
{
    protected $table = 'autoavaliacao_orientador_notas';

    protected $fillable = [
        'autoavaliacao_orientador_id',
        'barema_item_id',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'decimal:2',
        ];
    }
}
