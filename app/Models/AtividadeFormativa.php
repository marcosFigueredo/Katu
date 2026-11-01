<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Atividade formativa da lista do programa (vale para todos os cursos), definida pela Secretaria. */
class AtividadeFormativa extends Model
{
    protected $table = 'atividades_formativas';

    public const ETAPAS = [
        'qualificacao' => 'Qualificação',
        'defesa' => 'Defesa',
    ];

    public const INFORMADA_POR = [
        'estudante' => 'Estudante',
        'secretaria' => 'Secretaria',
    ];

    /** Lista inicial de todo programa (spec, "Declarações do estudante › C"). */
    public const PADROES = [
        ['nome' => 'Proficiência em Língua Estrangeira', 'informada_por' => 'secretaria'],
        ['nome' => 'Publicação de Produção Científica', 'informada_por' => 'estudante'],
        ['nome' => 'Atividade Extensionista', 'informada_por' => 'estudante'],
        ['nome' => 'Popularização das Ciências', 'informada_por' => 'estudante'],
    ];

    protected $fillable = [
        'programa_id',
        'nome',
        'detalhes',
        'quantidade_exigida',
        'etapa',
        'informada_por',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_exigida' => 'integer',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /** Cria a lista inicial quando o programa ainda não tem nenhuma atividade. */
    public static function criarPadroes(Programa $programa): void
    {
        if (self::where('programa_id', $programa->id)->exists()) {
            return;
        }

        foreach (self::PADROES as $ordem => $padrao) {
            self::create($padrao + [
                'programa_id' => $programa->id,
                'quantidade_exigida' => 1,
                'etapa' => 'defesa',
                'ativo' => true,
                'ordem' => $ordem + 1,
            ]);
        }
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function realizadas(): HasMany
    {
        return $this->hasMany(AtividadeRealizada::class);
    }
}
