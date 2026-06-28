<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoavaliacaoIndicador extends Model
{
    protected $table = 'autoavaliacao_indicadores';

    protected $fillable = [
        'autoavaliacao_periodo_id',
        'codigo',
        'nome',
        'valor_numerico',
        'valor_texto',
        'detalhes_json',
        'calculado_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_numerico' => 'float',
            'detalhes_json'  => 'array',
            'calculado_em'   => 'datetime',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(AutoavaliacaoPeriodo::class, 'autoavaliacao_periodo_id');
    }
}
