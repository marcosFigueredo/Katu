<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomologacaoRespostaCpa extends Model
{
    protected $table = 'homologacao_respostas_cpa';

    protected $fillable = [
        'banca_id',
        'criterio_cpa_id',
        'tentativa',
        'pergunta',
        'resposta',
        'respondido_por',
        'respondido_em',
    ];

    protected function casts(): array
    {
        return [
            'tentativa' => 'integer',
            'resposta' => 'boolean',
            'respondido_em' => 'datetime',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function criterioCpa(): BelongsTo
    {
        return $this->belongsTo(CriterioCpa::class, 'criterio_cpa_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }
}

