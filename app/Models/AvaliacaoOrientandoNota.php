<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoOrientandoNota extends Model
{
    protected $table = 'avaliacao_orientando_notas';

    protected $fillable = [
        'avaliacao_orientando_id',
        'elemento',
        'nota',
        'dimensao_qualidade_id',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'decimal:2',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoOrientando::class, 'avaliacao_orientando_id');
    }
}
