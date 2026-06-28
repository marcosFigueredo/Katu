<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
