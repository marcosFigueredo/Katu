<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Avaliação do orientando pelo orientador (defesa): fora do resultado da banca, privada à CPA. */
class AvaliacaoOrientando extends Model
{
    protected $table = 'avaliacoes_orientando';

    /** Elementos 1–4 e 6 da dimensão Autonomia do estudante (spec, dimensão 5). */
    public const ELEMENTOS = [
        'dominio_tema' => 'Domínio do tema',
        'dominio_metodo' => 'Domínio do método',
        'capacidade_critica' => 'Capacidade crítica',
        'independencia' => 'Independência nas decisões',
        'amadurecimento' => 'Amadurecimento',
    ];

    protected $fillable = [
        'banca_id',
        'docente_programa_vinculo_id',
        'observacoes',
        'enviada_em',
    ];

    protected function casts(): array
    {
        return [
            'enviada_em' => 'datetime',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(AvaliacaoOrientandoNota::class);
    }
}
