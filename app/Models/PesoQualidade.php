<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Peso de uma dimensão ou fonte no período, resultado do SWARA feito pela CPA. */
class PesoQualidade extends Model
{
    protected $table = 'pesos_qualidade';

    protected $fillable = ['periodo_qualidade_id', 'grupo', 'chave', 'ordem', 's_j', 'peso', 'definido_por'];

    protected function casts(): array
    {
        return ['s_j' => 'float', 'peso' => 'float'];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoQualidade::class, 'periodo_qualidade_id');
    }

    public function definidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'definido_por');
    }
}
