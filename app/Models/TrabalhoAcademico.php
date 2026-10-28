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

    /** Só as orientações vigentes; o histórico completo está em orientacoesHistorico(). */
    public function orientacoes()
    {
        return $this->hasMany(Orientacao::class)->where('ativo', true);
    }

    public function orientacoesHistorico()
    {
        return $this->hasMany(Orientacao::class)->orderBy('inicio_em')->orderBy('id');
    }

    public function orientadorVigente(): ?Orientacao
    {
        return $this->orientacoes()->where('tipo', 'orientador')->first();
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

    /**
     * Atualidade e produção do orientador da submissão mais recente com verificação concluída (D8),
     * para os relatórios. null quando nenhuma foi concluída.
     *
     * @return array{total: int, com_ano: int, recentes_pct: ?float, atualizadas: ?bool, orientador: int}|null
     */
    public function metricasReferencias(): ?array
    {
        $s = $this->submissoesLatex()->where('refs_status', 'concluida')->latest('id')->first();
        if ($s === null) {
            return null;
        }

        return [
            'total' => (int) $s->refs_total,
            'com_ano' => (int) $s->refs_com_ano,
            'recentes_pct' => $s->refs_recentes_pct !== null ? (float) $s->refs_recentes_pct : null,
            'atualizadas' => $s->refs_atualizadas,
            'orientador' => (int) $s->refs_orientador,
        ];
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
