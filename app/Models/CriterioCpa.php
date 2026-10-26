<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterioCpa extends Model
{
    protected $table = 'criterios_cpa';

    protected $fillable = [
        'programa_id',
        'criado_por',
        'pergunta',
        'etapa',
        'nivel',
        'ordem',
        'ativo',
        'inativado_em',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'inativado_em' => 'datetime',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** A pergunta nunca é apagada nem reativada: só inativada, com a data. */
    public function inativar(): void
    {
        $this->update(['ativo' => false, 'inativado_em' => now()]);
    }
}
