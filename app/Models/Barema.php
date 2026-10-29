<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Barema extends Model
{
    /** Tipos de barema e seus rótulos; a autoavaliação é preenchida pelo orientador na defesa. */
    public const TIPOS = [
        'qualificacao'              => 'Qualificação',
        'defesa'                    => 'Defesa',
        'qualificacao_confidencial' => 'Qualificação Confidencial',
        'defesa_confidencial'       => 'Defesa Confidencial',
        'autoavaliacao_orientador'  => 'Autoavaliação do orientador',
    ];

    protected $fillable = [
        'programa_id',
        'nome',
        'tipo',
        'nivel',
        'versao',
        'ativo',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function secoes(): HasMany
    {
        return $this->hasMany(BaremaSecao::class)->orderBy('ordem');
    }

    public function itens(): HasManyThrough
    {
        return $this->hasManyThrough(BaremaItem::class, BaremaSecao::class);
    }

    public function totalPontos(): float
    {
        return $this->secoes->flatMap->itens->sum(
            fn ($item) => (float) $item->nota_maxima * (float) $item->peso
        );
    }
}
