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

    /** Tipo de checklist de um tipo de documento: dissertação e tese seguem o checklist de defesa. */
    public static function tipoDoChecklist(string $tipoDocumento): string
    {
        return in_array($tipoDocumento, ['dissertacao', 'tese'], true) ? 'defesa' : $tipoDocumento;
    }

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