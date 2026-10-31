<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Espelho do discente na defesa: suas notas às perguntas do barema da banca; envio único, privado à CPA. */
class EspelhoDiscente extends Model
{
    protected $table = 'espelhos_discente';

    protected $fillable = [
        'banca_id',
        'barema_id',
        'nota_final',
        'enviada_em',
    ];

    protected function casts(): array
    {
        return [
            'nota_final' => 'decimal:2',
            'enviada_em' => 'datetime',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function barema(): BelongsTo
    {
        return $this->belongsTo(Barema::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(EspelhoDiscenteNota::class);
    }
}
