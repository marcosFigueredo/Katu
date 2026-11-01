<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Publicação da produção associada, indicada pelo estudante (spec, dimensão 4 › A). */
class ProducaoPublicacao extends Model
{
    protected $table = 'producao_publicacoes';

    public const TIPOS = [
        'artigo' => 'Artigo em periódico',
        'livro' => 'Livro',
        'capitulo' => 'Capítulo de livro',
        'anais' => 'Trabalho em anais',
    ];

    /** Canal por tipo; o artigo não tem (o fator de impacto vem do OpenAlex). */
    public const CANAIS = [
        'livro' => [
            'universitaria_regional' => 'Editora universitária ou regional',
            'comercial_nacional' => 'Editora comercial nacional com conselho editorial',
            'internacional' => 'Editora internacional',
        ],
        'capitulo' => [
            'universitaria_regional' => 'Editora universitária ou regional',
            'comercial_nacional' => 'Editora comercial nacional com conselho editorial',
            'internacional' => 'Editora internacional',
        ],
        'anais' => [
            'local_regional' => 'Evento local ou regional',
            'nacional' => 'Evento nacional',
            'internacional' => 'Evento internacional',
        ],
    ];

    protected $fillable = [
        'trabalho_academico_id',
        'tipo',
        'canal',
        'titulo',
        'ano',
        'veiculo',
        'doi',
        'issn',
        'openalex_id',
    ];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
