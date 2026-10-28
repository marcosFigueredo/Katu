<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um período de orientação ou coorientação. Vigente ⇔ ativo = true (fim_em nulo);
 * os períodos encerrados nunca são apagados.
 */
class Orientacao extends Model
{
    protected $table = 'orientacoes';

    protected $fillable = [
        'trabalho_academico_id',
        'discente_programa_vinculo_id',
        'docente_programa_vinculo_id',
        'tipo',
        'ordem',
        'ativo',
        'inicio_em',
        'fim_em',
        'processo_sei',
        'justificativa',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'ativo'     => 'boolean',
            'inicio_em' => 'date',
            'fim_em'    => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Orientacao $orientacao) {
            $orientacao->inicio_em ??= today();
        });
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function trabalhoAcademico()
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    public function discenteVinculo()
    {
        return $this->belongsTo(DiscenteProgramaVinculo::class, 'discente_programa_vinculo_id');
    }

    public function docenteProgramaVinculo()
    {
        return $this->belongsTo(DocenteProgramaVinculo::class, 'docente_programa_vinculo_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
