<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código, dados e ética da pesquisa, declarados pelo estudante (perguntas de submissão inspiradas na IEEE). */
class TrabalhoDadosPesquisa extends Model
{
    protected $table = 'trabalho_dados_pesquisa';

    public const CODIGO = [
        'nao_houve' => 'Não houve código próprio',
        'publico' => 'Sim — disponível em repositório público',
        'sob_solicitacao' => 'Sim — disponível mediante solicitação ao autor',
        'nao_compartilhavel' => 'Sim — não pode ser compartilhado',
    ];

    public const DADOS_TIPOS = [
        'primarios' => 'Primários, coletados pelo estudante',
        'secundarios_publicos' => 'Secundários de bases públicas (ex.: DATASUS, IBGE)',
        'secundarios_restritos' => 'Secundários de acesso restrito',
        'simulados' => 'Simulados ou gerados por modelo',
    ];

    public const DADOS_DISPONIBILIDADE = [
        'nao_se_aplica' => 'Não se aplica (pesquisa teórica, sem dados)',
        'publico' => 'Repositório público',
        'no_trabalho' => 'Incluídos no trabalho (apêndice ou material suplementar)',
        'sob_solicitacao' => 'Mediante solicitação ao autor',
        'nao_compartilhavel' => 'Não podem ser compartilhados',
    ];

    public const TCLE = [
        'termo' => 'Houve Termo de Consentimento Livre e Esclarecido',
        'dispensa' => 'Houve dispensa do Termo, justificada',
    ];

    protected $fillable = [
        'trabalho_academico_id',
        'codigo',
        'codigo_link',
        'codigo_justificativa',
        'dados_tipos',
        'dados_disponibilidade',
        'dados_link',
        'dados_justificativa',
        'seres_humanos',
        'caae',
        'parecer_numero',
        'tcle',
        'tcle_justificativa',
        'animais',
        'ceua_protocolo',
        'preenchido_em',
    ];

    protected function casts(): array
    {
        return [
            'dados_tipos' => 'array',
            'seres_humanos' => 'boolean',
            'animais' => 'boolean',
            'preenchido_em' => 'datetime',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }
}
