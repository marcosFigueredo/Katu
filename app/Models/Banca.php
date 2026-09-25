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
        'resultado',
        'nota_media',
        'data_hora',
        'local',
        'observacoes',
        'libera_envio_trabalho',
    ];

    protected function casts(): array
    {
        return [
            'data_hora'             => 'datetime',
            'libera_envio_trabalho' => 'boolean',
            'nota_media'            => 'float',
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

    public function evidencias()
    {
        return $this->hasMany(BancaEvidencia::class);
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
