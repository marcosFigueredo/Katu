<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resposta do estudante a uma recomendação da qualificação. */
class RecomendacaoResposta extends Model
{
    protected $table = 'recomendacao_respostas';

    public const SITUACOES = [
        'atendida' => 'Atendida',
        'parcial' => 'Atendida em parte',
        'nao_atendida' => 'Não atendida',
    ];

    protected $fillable = [
        'banca_recomendacao_id',
        'situacao',
        'onde',
        'justificativa',
    ];

    public function recomendacao(): BelongsTo
    {
        return $this->belongsTo(BancaRecomendacao::class, 'banca_recomendacao_id');
    }
}
