<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orientacao extends Model
{
    protected $table = 'orientacoes';

    protected $fillable = [
        'trabalho_academico_id',
        'docente_programa_vinculo_id',
        'tipo',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function trabalhoAcademico()
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    public function docenteProgramaVinculo()
    {
        return $this->belongsTo(DocenteProgramaVinculo::class, 'docente_programa_vinculo_id');
    }
}