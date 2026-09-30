<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoAnalise extends Model
{
    protected $table = 'documento_analises';

    protected $fillable = [
        'trabalho_documento_id',
        'documento_checklist_id',
        'status_geral',
        'percentual_conformidade',
        'total_itens',
        'itens_conformes',
        'itens_atencao',
        'itens_pendentes',
        'itens_nao_aplicaveis',
        'pdf_pesquisavel',
        'pdf_protegido',
        'classe_latex_detectada',
        'classe_latex_nome',
        'classe_latex_versao',
        'motor_tex',
        'codigo_certificacao',
        'texto_extraido',
        'mensagem_geral',
        'analisado_em',
    ];

    protected function casts(): array
    {
        return [
            'pdf_pesquisavel' => 'boolean',
            'pdf_protegido' => 'boolean',
            'classe_latex_detectada' => 'boolean',
            'analisado_em' => 'datetime',
        ];
    }

    public function trabalhoDocumento()
    {
        return $this->belongsTo(TrabalhoDocumento::class);
    }

    public function checklist()
    {
        return $this->belongsTo(DocumentoChecklist::class, 'documento_checklist_id');
    }

    public function itens()
    {
        return $this->hasMany(DocumentoAnaliseItem::class, 'documento_analise_id');
    }

    public function ollama()
    {
        return $this->hasOne(DocumentoAnaliseOllama::class, 'documento_analise_id');
    }

    public function conformidadeRag()
    {
        return $this->hasMany(DocumentoConformidadeRag::class, 'documento_analise_id');
    }
}