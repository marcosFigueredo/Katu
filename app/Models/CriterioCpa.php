<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterioCpa extends Model
{
    protected $table = 'criterios_cpa';

    protected $fillable = [
        'programa_id',
        'criado_por',
        'codigo',
        'titulo',
        'descricao',
        'etapa',
        'tipo_evidencia',
        'obrigatorio',
        'nivel',
        'ordem',
        'versao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'ativo' => 'boolean',
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

    public function evidencias(): HasMany
    {
        return $this->hasMany(BancaEvidencia::class);
    }
}
