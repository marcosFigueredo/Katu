<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma referência do .bbl da submissão e o resultado da verificação externa. */
class SubmissaoReferencia extends Model
{
    protected $table = 'submissao_referencias';

    public const STATUS = ['localizada', 'nao_localizada', 'nao_verificada'];

    protected $fillable = [
        'submissao_id',
        'chave',
        'texto',
        'doi_tex',
        'ano_tex',
        'titulo_tex',
        'status',
        'fonte',
        'doi_encontrado',
        'ano_encontrado',
        'divergencia_doi',
        'divergencia_ano',
        'do_orientador',
    ];

    protected function casts(): array
    {
        return [
            'divergencia_doi' => 'boolean',
            'divergencia_ano' => 'boolean',
            'do_orientador' => 'boolean',
        ];
    }

    public function submissao(): BelongsTo
    {
        return $this->belongsTo(TrabalhoSubmissaoLatex::class, 'submissao_id');
    }

    /** Ano considerado na atualidade (D8): o da verificação externa, senão o do .bbl. */
    public function anoConsiderado(): ?int
    {
        return $this->ano_encontrado ?? $this->ano_tex;
    }
}
