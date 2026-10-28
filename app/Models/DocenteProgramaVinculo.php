<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteProgramaVinculo extends Model
{
    protected $fillable = [
        'programa_id',
        'docente_profile_id',
        'tipo_vinculo',
        'categoria_docente',
        'pode_orientar',
        'pode_avaliar',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'pode_orientar' => 'boolean',
            'pode_avaliar' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function docenteProfile(): BelongsTo
    {
        return $this->belongsTo(DocenteProfile::class);
    }

public function orientacoes()
{
    return $this->hasMany(Orientacao::class, 'docente_programa_vinculo_id')->where('ativo', true);
}

public function bancaMembros()
{
    return $this->hasMany(BancaMembro::class, 'docente_programa_vinculo_id');
}
}
