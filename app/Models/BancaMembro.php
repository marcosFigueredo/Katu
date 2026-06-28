<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BancaMembro extends Model
{
    protected $table = 'banca_membros';

    protected $fillable = [
        'banca_id',
        'docente_programa_vinculo_id',
        'papel',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function banca()
    {
        return $this->belongsTo(Banca::class);
    }

    public function docenteProgramaVinculo()
    {
        return $this->belongsTo(DocenteProgramaVinculo::class, 'docente_programa_vinculo_id');
    }

    public function avaliacoes()
    {
        return $this->hasMany(BancaAvaliacao::class);
    }
}
