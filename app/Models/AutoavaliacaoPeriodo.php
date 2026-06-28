<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoavaliacaoPeriodo extends Model
{
    protected $table = 'autoavaliacao_periodos';

    protected $fillable = [
        'programa_id',
        'nome',
        'ano',
        'semestre',
        'data_inicio',
        'data_fim',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim'    => 'date',
            'ano'         => 'integer',
            'semestre'    => 'integer',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(AutoavaliacaoIndicador::class)->orderBy('codigo');
    }

    public function emAndamento(): bool
    {
        return $this->status === 'em_andamento';
    }
}
