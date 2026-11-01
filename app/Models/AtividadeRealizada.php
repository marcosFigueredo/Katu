<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Atividade formativa realizada pelo estudante (ou registrada pela Secretaria), conferida na homologação. */
class AtividadeRealizada extends Model
{
    protected $table = 'atividades_realizadas';

    public const CONFERENCIA = [
        'confirmada' => 'Confirmada',
        'nao_confirmada' => 'Não confirmada',
    ];

    protected $fillable = [
        'trabalho_academico_id',
        'atividade_formativa_id',
        'descricao',
        'link',
        'conferencia',
        'conferida_por',
        'conferida_em',
    ];

    protected function casts(): array
    {
        return [
            'conferida_em' => 'datetime',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    public function atividadeFormativa(): BelongsTo
    {
        return $this->belongsTo(AtividadeFormativa::class);
    }

    public function conferidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferida_por');
    }
}
