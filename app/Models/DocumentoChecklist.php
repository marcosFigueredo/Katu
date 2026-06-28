<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoChecklist extends Model
{
    protected $fillable = [
        'programa_id',
        'nome',
        'tipo_documento',
        'nivel',
        'versao',
        'ativo',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class);
    }

    public function itens()
    {
        return $this->hasMany(DocumentoChecklistItem::class);
    }

    public function analises()
    {
        return $this->hasMany(DocumentoAnalise::class);
    }
}