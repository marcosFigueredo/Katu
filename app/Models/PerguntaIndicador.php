<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerguntaIndicador extends Model
{
    protected $table = 'pergunta_indicadores';

    protected $fillable = [
        'pergunta_estrategica_id',
        'indicador_codigo',
        'direcao',
        'limiar_verde',
        'limiar_amarelo',
    ];

    protected function casts(): array
    {
        return [
            'limiar_verde'   => 'float',
            'limiar_amarelo' => 'float',
        ];
    }

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(PerguntaEstrategica::class, 'pergunta_estrategica_id');
    }
}
