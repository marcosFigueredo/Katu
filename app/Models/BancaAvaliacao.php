<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BancaAvaliacao extends Model
{
    protected $table = 'banca_avaliacoes';

    protected $fillable = [
        'banca_id',
        'banca_membro_id',
        'barema_id',
        'status',
        'nota_final',
        'observacoes_gerais',
        'plagio_identificado',
        'plagio_indicacao',
        'enviada_em',
    ];

    protected function casts(): array
    {
        return [
            'nota_final' => 'float',
            'enviada_em' => 'datetime',
            'plagio_identificado' => 'boolean',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function membro(): BelongsTo
    {
        return $this->belongsTo(BancaMembro::class, 'banca_membro_id');
    }

    public function barema(): BelongsTo
    {
        return $this->belongsTo(Barema::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(BancaAvaliacaoNota::class);
    }

    public function notaCalculada(): ?float
    {
        $notas = $this->notas->filter(fn ($n) => $n->nota !== null && $n->baremaItem !== null);

        if ($notas->isEmpty()) {
            return null;
        }

        $totalPossivel = $notas->sum(
            fn ($n) => (float) $n->baremaItem->nota_maxima * (float) $n->baremaItem->peso
        );

        if ($totalPossivel <= 0) {
            return null;
        }

        $totalObtido = $notas->sum(
            fn ($n) => (float) $n->nota * (float) $n->baremaItem->peso
        );

        return round(($totalObtido / $totalPossivel) * 10, 2);
    }
}
