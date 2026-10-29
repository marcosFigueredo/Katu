<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Banca extends Model
{
    protected $fillable = [
        'trabalho_academico_id',
        'tipo',
        'status',
        'homologacao_status',
        'documento_homologacao_id',
        'homologacao_tentativa',
        'resultado',
        'nota_media',
        'data_hora',
        'local',
        'observacoes',
        'homologacao_observacoes',
        'homologada_por',
        'homologada_em',
        'enviada_banca_em',
        'ata_enviada_em',
        'devolvida_por',
        'devolvida_em',
        'libera_envio_trabalho',
    ];

    protected function casts(): array
    {
        return [
            'data_hora'             => 'datetime',
            'libera_envio_trabalho' => 'boolean',
            'nota_media'            => 'float',
            'homologada_em'         => 'datetime',
            'enviada_banca_em'      => 'datetime',
            'ata_enviada_em'        => 'datetime',
            'devolvida_em'          => 'datetime',
            'homologacao_tentativa' => 'integer',
        ];
    }

    public static function resultadoLabel(string $resultado): string
    {
        return match ($resultado) {
            'aprovado'              => 'Aprovado',
            'aprovado_modificacoes' => 'Aprovado com modificações',
            'reprovado'             => 'Reprovado',
            default                 => $resultado,
        };
    }

    public function trabalhoAcademico()
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    /** Documento (PDF) enviado pelo discente no pedido de homologação. */
    public function documentoHomologacao()
    {
        return $this->belongsTo(TrabalhoDocumento::class, 'documento_homologacao_id');
    }

    public function homologadaPor()
    {
        return $this->belongsTo(User::class, 'homologada_por');
    }

    public function devolvidaPor()
    {
        return $this->belongsTo(User::class, 'devolvida_por');
    }

    public function autoavaliacaoOrientador(): HasOne
    {
        return $this->hasOne(AutoavaliacaoOrientador::class);
    }

    public function respostasCpa(): HasMany
    {
        return $this->hasMany(HomologacaoRespostaCpa::class);
    }

    public function membros()
    {
        return $this->hasMany(BancaMembro::class);
    }

    public function avaliadores()
    {
        return $this->membros()->where('papel', 'avaliador')->where('ativo', true);
    }

    public function avaliacoes()
    {
        return $this->hasMany(BancaAvaliacao::class);
    }

    public function indicadores(): HasOne
    {
        return $this->hasOne(BancaIndicador::class);
    }

    public function orientacaoAvaliacoes()
    {
        return $this->hasMany(OrientacaoAvaliacao::class);
    }

}
