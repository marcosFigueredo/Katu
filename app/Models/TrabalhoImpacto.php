<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoImpacto extends Model
{
    protected $table = 'trabalho_impactos';

    protected $fillable = [
        'trabalho_academico_id',
        'impacto_cientifico_aplicavel',
        'impacto_cientifico_descricao',
        'impacto_metodologico_aplicavel',
        'impacto_metodologico_descricao',
        'impacto_tecnologico_aplicavel',
        'impacto_tecnologico_descricao',
        'impacto_social_aplicavel',
        'impacto_social_descricao',
        'impacto_ambiental_aplicavel',
        'impacto_ambiental_descricao',
        'impacto_formativo_aplicavel',
        'impacto_formativo_descricao',
        'impacto_institucional_aplicavel',
        'impacto_institucional_descricao',
    ];

    protected function casts(): array
    {
        return [
            'impacto_cientifico_aplicavel'    => 'boolean',
            'impacto_metodologico_aplicavel'  => 'boolean',
            'impacto_tecnologico_aplicavel'   => 'boolean',
            'impacto_social_aplicavel'        => 'boolean',
            'impacto_ambiental_aplicavel'     => 'boolean',
            'impacto_formativo_aplicavel'     => 'boolean',
            'impacto_institucional_aplicavel' => 'boolean',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
