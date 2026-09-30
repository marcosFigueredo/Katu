<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaDocumentoReferencia extends Model
{
    protected $table = 'programa_documentos_referencia';

    protected $fillable = [
        'programa_id',
        'tipo',
        'arquivo_path',
        'arquivo_nome_original',
        'tamanho',
        'hash_sha256',
        'enviado_por',
        'enviado_em',
        'status_indexacao',
        'indexado_em',
        'erro_indexacao',
    ];

    protected function casts(): array
    {
        return [
            'enviado_em' => 'datetime',
            'indexado_em' => 'datetime',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(BaseConhecimentoChunk::class);
    }
}
