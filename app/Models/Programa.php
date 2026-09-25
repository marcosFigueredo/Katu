<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programa extends Model
{
    protected $fillable = [
        'nome',
        'sigla',
        'codigo_capes',
        'area_avaliacao',
        'oferta_mestrado',
        'oferta_doutorado',
        'email',
        'site',
        'telefone',
        'descricao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'oferta_mestrado' => 'boolean',
            'oferta_doutorado' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function areasConcentracao(): HasMany
    {
        return $this->hasMany(AreaConcentracao::class);
    }
    public function docenteVinculos(): HasMany
    {
        return $this->hasMany(DocenteProgramaVinculo::class);
    }

    public function discenteVinculos(): HasMany
    {
        return $this->hasMany(DiscenteProgramaVinculo::class);
    }
    public function programaUsuarios(): HasMany
{
    return $this->hasMany(ProgramaUsuario::class);
}
public function trabalhosAcademicos()
{
    return $this->hasMany(TrabalhoAcademico::class);
}

    public function documentoChecklists()
    {
        return $this->hasMany(DocumentoChecklist::class);
    }

    public function criteriosCpa(): HasMany
    {
        return $this->hasMany(CriterioCpa::class);
    }

}
