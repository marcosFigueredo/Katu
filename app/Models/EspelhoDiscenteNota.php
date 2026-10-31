<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EspelhoDiscenteNota extends Model
{
    protected $table = 'espelho_discente_notas';

    protected $fillable = [
        'espelho_discente_id',
        'barema_item_id',
        'dimensao_qualidade_id',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'decimal:2',
        ];
    }
}
