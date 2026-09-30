<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseConhecimentoChunk extends Model
{
    protected $table = 'base_conhecimento_chunks';

    protected $fillable = [
        'programa_documento_referencia_id',
        'programa_id',
        'tipo',
        'pagina',
        'ordem',
        'conteudo',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public function documentoReferencia(): BelongsTo
    {
        return $this->belongsTo(ProgramaDocumentoReferencia::class, 'programa_documento_referencia_id');
    }
}
