<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Informações adicionais da submissão da defesa, declaradas pelo estudante; a banca vê as respostas. */
class TrabalhoInformacoesAdicionais extends Model
{
    protected $table = 'trabalho_informacoes_adicionais';

    public const DISPONIBILIDADE = [
        'nenhum_dado_novo' => 'Nenhum dado novo foi gerado ou analisado neste estudo',
        'no_trabalho' => 'Os dados disponíveis estão incluídos no trabalho ou em seus materiais suplementares',
        'sob_solicitacao' => 'Os conjuntos de dados originais que sustentam os resultados estão disponíveis e serão compartilhados pelo autor mediante solicitação razoável',
        'repositorio_publico' => 'Os conjuntos de dados originais estão publicamente disponíveis no seguinte repositório',
    ];

    /** Documentos de ética em PDF: aprovação (pergunta 4) e certidão do comitê (pergunta 5). */
    public const DOCUMENTOS = [
        'aprovacao' => 'Documento de aprovação ética',
        'certidao' => 'Certidão do comitê de ética',
    ];

    protected $fillable = [
        'trabalho_academico_id',
        'conflito_interesses',
        'conflito_descricao',
        'disponibilidade_dados',
        'dados_repositorio',
        'amostras_fisicas',
        'amostras_declaracao',
        'aprovacao_etica',
        'aprovacao_etica_path',
        'aprovacao_etica_nome',
        'participantes',
        'certidao_etica_path',
        'certidao_etica_nome',
        'uso_ia',
        'ia_ferramentas',
        'ia_etapas',
        'originalidade_confirmada',
        'preenchido_em',
    ];

    protected function casts(): array
    {
        return [
            'conflito_interesses' => 'boolean',
            'amostras_fisicas' => 'boolean',
            'aprovacao_etica' => 'boolean',
            'participantes' => 'boolean',
            'uso_ia' => 'boolean',
            'originalidade_confirmada' => 'boolean',
            'preenchido_em' => 'datetime',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
