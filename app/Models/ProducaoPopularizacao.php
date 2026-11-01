<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ação de popularização das ciências da produção associada (spec, dimensão 4 › D). */
class ProducaoPopularizacao extends Model
{
    protected $table = 'producao_popularizacoes';

    public const TIPOS = [
        'palestra' => 'Palestra aberta',
        'feira' => 'Feira de ciências',
        'imprensa' => 'Imprensa',
        'video' => 'Vídeo',
        'podcast' => 'Podcast',
        'rede_social' => 'Rede social',
        'material_didatico' => 'Material didático',
    ];

    protected $fillable = [
        'trabalho_academico_id',
        'tipo',
        'titulo',
        'veiculo',
        'alcance',
        'data',
        'link',
    ];

    protected function casts(): array
    {
        return [
            'alcance' => 'integer',
            'data' => 'date',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
