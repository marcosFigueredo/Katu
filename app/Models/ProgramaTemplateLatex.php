<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaTemplateLatex extends Model
{
    protected $table = 'programa_templates_latex';

    protected $fillable = [
        'programa_id',
        'arquivo_path',
        'arquivo_nome_original',
        'hash_sha256',
        'tex_principal',
        'classe_documento',
        'motor',
        'mapa_elementos',
        'enviado_por',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'mapa_elementos' => 'array',
            'enviado_em' => 'datetime',
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
}
