<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoRegraBanca extends Model
{
    protected $table = 'curso_regras_banca';

    protected $fillable = [
        'programa_curso_id',
        'etapa',
        'min_avaliadores',
        'max_avaliadores',
        'min_internos',
        'min_externos_instituicao',
    ];

    protected function casts(): array
    {
        return [
            'min_avaliadores' => 'integer',
            'max_avaliadores' => 'integer',
            'min_internos' => 'integer',
            'min_externos_instituicao' => 'integer',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(ProgramaCurso::class, 'programa_curso_id');
    }
}
