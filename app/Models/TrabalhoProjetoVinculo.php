<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Período em que o trabalho esteve ligado a um projeto, com o objetivo que o estudante indicou. */
class TrabalhoProjetoVinculo extends Model
{
    public const MOTIVOS = [
        'orientador' => 'Orientador definido',
        'troca_orientador' => 'Troca de orientador',
        'novo_projeto' => 'Novo projeto do orientador',
    ];

    protected $fillable = [
        'trabalho_academico_id', 'projeto_pesquisa_id', 'motivo', 'inicio_em', 'fim_em', 'ativo',
        'objetivo', 'mantem_objetivo', 'objetivo_informado_em',
    ];

    protected function casts(): array
    {
        return [
            'inicio_em' => 'date',
            'fim_em' => 'date',
            'ativo' => 'boolean',
            'mantem_objetivo' => 'boolean',
            'objetivo_informado_em' => 'datetime',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(ProjetoPesquisa::class, 'projeto_pesquisa_id');
    }
}
