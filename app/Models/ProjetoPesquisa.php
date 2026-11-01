<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Projeto de pesquisa de um docente permanente; os orientandos são vinculados ao ativo. */
class ProjetoPesquisa extends Model
{
    protected $table = 'projetos_pesquisa';

    public const SITUACOES = ['ativo' => 'Ativo', 'concluido' => 'Concluído'];

    protected $fillable = [
        'docente_programa_vinculo_id', 'titulo', 'descricao', 'inicio_em', 'fim_em', 'situacao', 'registrado_por',
    ];

    protected function casts(): array
    {
        return ['inicio_em' => 'date', 'fim_em' => 'date'];
    }

    public function docenteProgramaVinculo(): BelongsTo
    {
        return $this->belongsTo(DocenteProgramaVinculo::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(TrabalhoProjetoVinculo::class);
    }
}
