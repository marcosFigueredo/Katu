<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwaraSessao extends Model
{
    protected $table = 'swara_sessoes';

    protected $fillable = [
        'barema_id',
        'nome',
        'descricao',
        'aprovado_por',
        'aprovado_em',
        'aplicado_em',
    ];

    protected function casts(): array
    {
        return [
            'aprovado_em' => 'datetime',
            'aplicado_em' => 'datetime',
        ];
    }

    public function barema(): BelongsTo
    {
        return $this->belongsTo(Barema::class);
    }

    public function julgamentos(): HasMany
    {
        return $this->hasMany(SwaraJulgamento::class)->orderBy('ordem');
    }

    public function foiAplicada(): bool
    {
        return $this->aplicado_em !== null;
    }
}
