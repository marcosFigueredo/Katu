<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrabalhoDocumento extends Model
{
    protected $fillable = [
        'trabalho_academico_id',
        'enviado_por_id',
        'tipo_documento',
        'versao',
        'arquivo_pdf_path',
        'arquivo_pdf_nome_original',
        'arquivo_pdf_mime',
        'arquivo_pdf_tamanho',
        'arquivo_pdf_hash_sha256',
        'paginas_total',
        'status_analise',
        'enviado_em',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'enviado_em' => 'datetime',
        ];
    }

    public function trabalhoAcademico()
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    public function enviadoPor()
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }

    public function analises()
    {
        return $this->hasMany(DocumentoAnalise::class);
    }

    public function ultimaAnalise()
    {
        return $this->hasOne(DocumentoAnalise::class)->latestOfMany();
    }
}