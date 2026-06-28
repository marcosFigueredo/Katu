<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrcidSnapshot extends Model
{
    protected $fillable = [
        'docente_profile_id',
        'orcid_id',
        'url_orcid',
        'nome_publico',
        'total_obras',
        'total_obras_com_doi',
        'indice_h',
        'total_citacoes',
        'i10_index',
        'works_count_openalex',
        'dados_json',
        'status_consulta',
        'mensagem_erro',
        'consultado_em',
    ];

    protected function casts(): array
    {
        return [
            'dados_json'    => 'array',
            'consultado_em' => 'datetime',
        ];
    }

    public function docenteProfile(): BelongsTo
    {
        return $this->belongsTo(DocenteProfile::class);
    }
}
