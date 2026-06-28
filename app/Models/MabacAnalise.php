<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MabacAnalise extends Model
{
    protected $table = 'mabac_analises';

    protected $fillable = [
        'barema_id',
        'linha_pesquisa_id',
        'nome',
        'tipo_banca',
        'periodo_inicio',
        'periodo_fim',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim'    => 'date',
        ];
    }

    public function barema(): BelongsTo
    {
        return $this->belongsTo(Barema::class);
    }

    public function linhaPesquisa(): BelongsTo
    {
        return $this->belongsTo(LinhaPesquisa::class);
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(MabacResultado::class)->orderBy('ranking');
    }
}
