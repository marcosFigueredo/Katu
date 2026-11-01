<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Recomendação de um avaliador na qualificação; o estudante responde na defesa. */
class BancaRecomendacao extends Model
{
    protected $table = 'banca_recomendacoes';

    protected $fillable = [
        'banca_id',
        'banca_membro_id',
        'texto',
        'ordem',
    ];

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function resposta(): HasOne
    {
        return $this->hasOne(RecomendacaoResposta::class);
    }
}
