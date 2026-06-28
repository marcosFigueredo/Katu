<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoChecklistItem extends Model
{
    protected $table = 'documento_checklist_itens';

    protected $fillable = [
        'documento_checklist_id',
        'grupo',
        'nome',
        'descricao',
        'obrigatorio',
        'impeditivo',
        'status_se_ausente',
        'tipo_regra',
        'termos_busca',
        'ordem_esperada',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'impeditivo' => 'boolean',
            'termos_busca' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function checklist()
    {
        return $this->belongsTo(DocumentoChecklist::class, 'documento_checklist_id');
    }

    public function analiseItens()
    {
        return $this->hasMany(DocumentoAnaliseItem::class, 'documento_checklist_item_id');
    }
}