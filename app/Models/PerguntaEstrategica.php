<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerguntaEstrategica extends Model
{
    protected $table = 'perguntas_estrategicas';

    protected $fillable = [
        'programa_id',
        'item_capes',
        'texto',
        'origem',
        'ativa',
        'criado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'ativa' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(PerguntaIndicador::class);
    }
}
