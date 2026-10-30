<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaremaItem extends Model
{
    protected $table = 'barema_itens';

    protected $fillable = [
        'barema_secao_id',
        'dimensao_qualidade_id',
        'nome',
        'descricao',
        'nota_maxima',
        'peso',
        'obrigatorio',
        'indicador_capes',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo'      => 'boolean',
            'obrigatorio'=> 'boolean',
            'nota_maxima'=> 'float',
            'peso'       => 'float',
        ];
    }

    public function dimensao(): BelongsTo
    {
        return $this->belongsTo(DimensaoQualidade::class, 'dimensao_qualidade_id');
    }

    public function secao(): BelongsTo
    {
        return $this->belongsTo(BaremaSecao::class, 'barema_secao_id');
    }
}
