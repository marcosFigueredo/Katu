<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscenteProgramaVinculo extends Model
{
    protected $fillable = [
        'programa_id',
        'discente_profile_id',
        'linha_pesquisa_id',
        'matricula',
        'nivel',
        'status',
        'data_ingresso',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_ingresso' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function discenteProfile(): BelongsTo
    {
        return $this->belongsTo(DiscenteProfile::class);
    }

    public function linhaPesquisa(): BelongsTo
    {
        return $this->belongsTo(LinhaPesquisa::class);
    }
    public function trabalhoAcademico()
    {
        return $this->hasOne(TrabalhoAcademico::class, 'discente_programa_vinculo_id');
    }
}
