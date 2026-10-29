<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Autoavaliação do orientador na banca de defesa: envio único, respostas privadas à CPA. */
class AutoavaliacaoOrientador extends Model
{
    protected $table = 'autoavaliacoes_orientador';

    protected $fillable = [
        'banca_id',
        'docente_programa_vinculo_id',
        'barema_id',
        'nota_final',
        'observacoes',
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
        return $this->hasMany(AutoavaliacaoOrientadorNota::class);
    }
}
