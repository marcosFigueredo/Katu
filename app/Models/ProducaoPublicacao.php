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

    public const SITUACOES = [
        'publicada' => 'Publicada',
        'aceita' => 'Aceita',
        'submetida' => 'Submetida',
    ];

    /** Marcações da Secretaria (sim/não) para análise futura. */
    public const MARCACOES = [
        'derivada_dissertacao' => 'Saiu da dissertação',
        'discente_primeiro_autor' => 'Estudante primeiro autor',
        'coautoria_orientador' => 'Orientador coautor',
        'coautoria_internacional' => 'Coautoria internacional',
        'conta_minimo' => 'Conta para o mínimo do curso',
    ];

    public const OPENALEX_SITUACOES = [
        'encontrada' => 'Encontrada no OpenAlex',
        'nao_encontrada' => 'Não encontrada no OpenAlex',
        'falha' => 'OpenAlex indisponível; nova tentativa depois',
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
        'openalex_situacao',
        'openalex_citacoes',
        'openalex_verificada_em',
        'derivada_dissertacao',
        'situacao',
        'discente_primeiro_autor',
        'coautoria_orientador',
        'coautoria_internacional',
        'conta_minimo',
        'conferida_por',
        'conferida_em',
    ];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'derivada_dissertacao' => 'boolean',
            'discente_primeiro_autor' => 'boolean',
            'coautoria_orientador' => 'boolean',
            'coautoria_internacional' => 'boolean',
            'conta_minimo' => 'boolean',
            'conferida_em' => 'datetime',
            'openalex_citacoes' => 'integer',
            'openalex_verificada_em' => 'datetime',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
