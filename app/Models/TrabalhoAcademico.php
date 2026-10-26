<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrabalhoAcademico extends Model
{
    protected $table = 'trabalhos_academicos';
    protected $fillable = [
        'programa_id',
        'discente_programa_vinculo_id',
        'linha_pesquisa_id',
        'titulo',
        'resumo',
        'palavras_chave',
        'nivel',
        'status',
        'data_inicio',
        'data_prevista_qualificacao',
        'data_prevista_defesa',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_prevista_qualificacao' => 'date',
            'data_prevista_defesa' => 'date',
        ];
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class);
    }

    public function discenteVinculo()
    {
        return $this->belongsTo(DiscenteProgramaVinculo::class, 'discente_programa_vinculo_id');
    }

    public function linhaPesquisa()
    {
        return $this->belongsTo(LinhaPesquisa::class, 'linha_pesquisa_id');
    }

    public function documentos()
    {
        return $this->hasMany(TrabalhoDocumento::class);
    }

    public function orientacoes()
    {
        return $this->hasMany(Orientacao::class);
    }

    public function bancas()
    {
        return $this->hasMany(Banca::class);
    }

    public function indicador(): HasOne
    {
        return $this->hasOne(TrabalhoIndicador::class);
    }

    public function impacto(): HasOne
    {
        return $this->hasOne(TrabalhoImpacto::class);
    }

    public function submissoesLatex(): HasMany
    {
        return $this->hasMany(TrabalhoSubmissaoLatex::class);
    }

    /** Submissão LaTeX mais recente: a única que pode seguir à homologação. */
    public function submissaoLatexVigente(): HasOne
    {
        return $this->hasOne(TrabalhoSubmissaoLatex::class)->latestOfMany();
    }

    public function bancaQualificacao()
    {
        return $this->hasOne(Banca::class)->where('tipo', 'qualificacao');
    }

    public function bancaDefesa()
    {
        return $this->hasOne(Banca::class)->where('tipo', 'defesa');
    }
}
