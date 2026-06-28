<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MabacResultado extends Model
{
    protected $table = 'mabac_resultados';

    protected $fillable = [
        'mabac_analise_id',
        'banca_id',
        'escore_final',
        'ranking',
        'detalhes_json',
    ];

    protected function casts(): array
    {
        return [
            'escore_final'  => 'float',
            'detalhes_json' => 'array',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(MabacAnalise::class, 'mabac_analise_id');
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function interpretacao(): string
    {
        return match (true) {
            $this->escore_final > 0.5  => 'Acima da fronteira',
            $this->escore_final > -0.5 => 'Próximo à fronteira',
            default                    => 'Abaixo da fronteira',
        };
    }
}
