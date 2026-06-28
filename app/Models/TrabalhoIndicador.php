<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoIndicador extends Model
{
    protected $table = 'trabalho_indicadores';

    protected $fillable = [
        'trabalho_academico_id',
        'total_publicacoes_discente',
        'total_com_doi',
        'total_citacoes_crossref',
        'coautoria_orientador',
        'coautoria_internacional',
        'presenca_bases_indexadas',
        'dados_json',
        'consultado_em',
    ];

    protected function casts(): array
    {
        return [
            'coautoria_orientador'    => 'boolean',
            'coautoria_internacional' => 'boolean',
            'presenca_bases_indexadas'=> 'boolean',
            'dados_json'              => 'array',
            'consultado_em'           => 'datetime',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
