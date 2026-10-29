<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Programa extends Model
{
    protected $fillable = [
        'instituicao_id',
        'conceito_capes',
        'ano_inicio',
        'coordenador_id',
        'vice_coordenador_id',
        'max_coorientadores',
        'logo_path',
        'nome',
        'sigla',
        'codigo_capes',
        'area_avaliacao',
        'oferta_mestrado',
        'oferta_doutorado',
        'email',
        'site',
        'homepage_trabalhos',
        'telefone',
        'descricao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'oferta_mestrado' => 'boolean',
            'oferta_doutorado' => 'boolean',
            'ativo' => 'boolean',
            'max_coorientadores' => 'integer',
        ];
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class);
    }

    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordenador_id');
    }

    public function viceCoordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vice_coordenador_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(ProgramaCurso::class)->orderBy('nivel')->orderBy('modalidade');
    }

    public function areasConcentracao(): HasMany
    {
        return $this->hasMany(AreaConcentracao::class);
    }
    public function docenteVinculos(): HasMany
    {
        return $this->hasMany(DocenteProgramaVinculo::class);
    }

    public function discenteVinculos(): HasMany
    {
        return $this->hasMany(DiscenteProgramaVinculo::class);
    }
    public function programaUsuarios(): HasMany
{
    return $this->hasMany(ProgramaUsuario::class);
}
public function trabalhosAcademicos()
{
    return $this->hasMany(TrabalhoAcademico::class);
}

    public function documentoChecklists()
    {
        return $this->hasMany(DocumentoChecklist::class);
    }

    public function criteriosCpa(): HasMany
    {
        return $this->hasMany(CriterioCpa::class);
    }

    public function documentosReferencia(): HasMany
    {
        return $this->hasMany(ProgramaDocumentoReferencia::class);
    }

    public function templateLatex(): HasOne
    {
        return $this->hasOne(ProgramaTemplateLatex::class);
    }

    public function temTemplateLatex(): bool
    {
        return $this->templateLatex()->exists();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path && str_starts_with($this->logo_path, 'logos/')
            ? route('programas.logo', $this)
            : null;
    }
}
