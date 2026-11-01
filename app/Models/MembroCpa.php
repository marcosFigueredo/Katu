<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membro da CPA do programa, com mandato (início e fim) e o ato de designação. */
class MembroCpa extends Model
{
    protected $table = 'membros_cpa';

    protected $fillable = ['programa_id', 'user_id', 'inicio_em', 'fim_em', 'numero_ato', 'registrado_por'];

    protected function casts(): array
    {
        return ['inicio_em' => 'date', 'fim_em' => 'date'];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereDate('inicio_em', '<=', today())
            ->where(fn ($q) => $q->whereNull('fim_em')->orWhereDate('fim_em', '>=', today()));
    }

    public function vigente(): bool
    {
        return $this->inicio_em->lte(today()) && ($this->fim_em === null || $this->fim_em->gte(today()));
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
