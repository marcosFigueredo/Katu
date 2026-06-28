<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoAnaliseOllama extends Model
{
    protected $table = 'documento_analise_ollamas';

    protected $fillable = [
        'documento_analise_id',
        'modelo',
        'prompt',
        'resposta',
        'status',
        'erro',
        'gerado_em',
    ];

    protected function casts(): array
    {
        return [
            'resposta'  => 'array',
            'gerado_em' => 'datetime',
        ];
    }

    public function analise()
    {
        return $this->belongsTo(DocumentoAnalise::class, 'documento_analise_id');
    }
}