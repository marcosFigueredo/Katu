<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoConformidadeRag extends Model
{
    protected $table = 'documento_conformidade_rag';

    protected $fillable = [
        'documento_analise_id',
        'verificacao',
        'status',
        'veredito',
        'justificativa',
        'trecho_norma_citado',
        'erro',
    ];

    public function analise(): BelongsTo
    {
        return $this->belongsTo(DocumentoAnalise::class, 'documento_analise_id');
    }
}
