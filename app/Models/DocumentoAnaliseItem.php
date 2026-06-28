<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoAnaliseItem extends Model
{
    protected $table = 'documento_analise_itens';

    protected $fillable = [
        'documento_analise_id',
        'documento_checklist_item_id',
        'nome_item',
        'grupo',
        'status',
        'obrigatorio',
        'impeditivo',
        'evidencia',
        'pagina_detectada',
        'termos_encontrados',
        'mensagem',
    ];

    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'impeditivo' => 'boolean',
            'termos_encontrados' => 'array',
        ];
    }

    public function analise()
    {
        return $this->belongsTo(DocumentoAnalise::class, 'documento_analise_id');
    }

    public function checklistItem()
    {
        return $this->belongsTo(DocumentoChecklistItem::class, 'documento_checklist_item_id');
    }
}