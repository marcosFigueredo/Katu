<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancaEvidencia extends Model
{
    protected $table = 'banca_evidencias';

    protected $fillable = [
        'banca_id',
        'criterio_cpa_id',
        'status',
        'valor_texto',
        'arquivo_path',
        'arquivo_nome',
        'observacoes',
        'informado_por',
        'informado_em',
        'validado_por',
        'validado_em',
    ];

    protected function casts(): array
    {
        return [
            'informado_em' => 'datetime',
            'validado_em' => 'datetime',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioCpa::class, 'criterio_cpa_id');
    }

    public function informante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'informado_por');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}
