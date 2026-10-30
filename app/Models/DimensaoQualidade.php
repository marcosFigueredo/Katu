<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dimensão da qualidade das dissertações e teses; cada pergunta de barema pertence a uma. */
class DimensaoQualidade extends Model
{
    protected $table = 'dimensoes_qualidade';

    protected $fillable = ['codigo', 'nome', 'ordem', 'descricao'];

    /** As 8 dimensões, na ordem (docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md). */
    public const PADRAO = [
        ['codigo' => 'rigor', 'nome' => 'Rigor científico', 'ordem' => 1],
        ['codigo' => 'originalidade', 'nome' => 'Originalidade', 'ordem' => 2],
        ['codigo' => 'relevancia', 'nome' => 'Relevância e impacto', 'ordem' => 3],
        ['codigo' => 'producao', 'nome' => 'Produção associada', 'ordem' => 4],
        ['codigo' => 'autonomia', 'nome' => 'Autonomia do estudante', 'ordem' => 5],
        ['codigo' => 'aderencia', 'nome' => 'Aderência ao programa', 'ordem' => 6],
        ['codigo' => 'processo', 'nome' => 'Processo formativo', 'ordem' => 7],
        ['codigo' => 'integridade', 'nome' => 'Integridade e conformidade', 'ordem' => 8],
    ];

    public static function doCodigo(string $codigo): self
    {
        return static::where('codigo', $codigo)->firstOrFail();
    }
}
