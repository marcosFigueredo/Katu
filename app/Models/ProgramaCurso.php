<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaCurso extends Model
{
    public const NIVEIS = ['mestrado', 'doutorado'];
    public const MODALIDADES = ['academico', 'profissional'];
    public const ETAPAS = ['qualificacao', 'defesa'];

    /** Prazos (meses) e regra de banca [min, max, internos, externos à instituição] por nível. */
    public const PADROES = [
        'mestrado' => ['prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'min_publicacoes_defesa' => 1, 'regra' => [2, 5, 1, 1]],
        'doutorado' => ['prazo_qualificacao_meses' => 30, 'prazo_defesa_meses' => 48, 'min_publicacoes_defesa' => 4, 'regra' => [5, 5, 2, 2]],
    ];

    protected $table = 'programa_cursos';

    protected $fillable = [
        'programa_id',
        'nivel',
        'modalidade',
        'prazo_qualificacao_meses',
        'prazo_defesa_meses',
        'min_publicacoes_defesa',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'prazo_qualificacao_meses' => 'integer',
            'min_publicacoes_defesa' => 'integer',
            'prazo_defesa_meses' => 'integer',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function regrasBanca(): HasMany
    {
        return $this->hasMany(CursoRegraBanca::class);
    }

    public function regraPara(string $etapa): ?CursoRegraBanca
    {
        return $this->regrasBanca->firstWhere('etapa', $etapa);
    }

    protected function nome(): Attribute
    {
        return Attribute::get(fn () => ucfirst($this->nivel) . ' ' . ($this->modalidade === 'academico' ? 'Acadêmico' : 'Profissional'));
    }

    protected function identificador(): Attribute
    {
        return Attribute::get(fn () => $this->nivel . '_' . $this->modalidade);
    }
}
