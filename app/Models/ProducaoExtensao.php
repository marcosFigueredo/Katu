<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Atividade de extensão da produção associada (spec, dimensão 4 › C). */
class ProducaoExtensao extends Model
{
    protected $table = 'producao_extensoes';

    public const TIPOS = [
        'curso' => 'Curso',
        'oficina' => 'Oficina',
        'evento' => 'Evento',
        'prestacao_servico' => 'Prestação de serviço',
        'projeto' => 'Projeto',
    ];

    protected $fillable = [
        'trabalho_academico_id',
        'tipo',
        'titulo',
        'publico',
        'parceiro',
        'carga_horaria',
        'inicio',
        'fim',
        'registro_proex',
        'produto_entregue',
        'link',
    ];

    protected function casts(): array
    {
        return [
            'publico' => 'integer',
            'carga_horaria' => 'integer',
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
