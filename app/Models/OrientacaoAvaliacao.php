<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrientacaoAvaliacao extends Model
{
    protected $table = 'orientacao_avaliacoes';

    protected $fillable = [
        'banca_id',
        'discente_programa_vinculo_id',
        'docente_programa_vinculo_id',
        'item_11_objetivo',
        'item_12',
        'item_21', 'item_22',
        'item_31', 'item_32',
        'item_41', 'item_42',
        'item_51', 'item_52',
        'item_61', 'item_62',
        'nota_cat1', 'nota_cat2', 'nota_cat3',
        'nota_cat4', 'nota_cat5', 'nota_cat6',
        'ieo_final',
        'respondido_em',
    ];

    protected function casts(): array
    {
        return [
            'respondido_em' => 'datetime',
        ];
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function discenteProgramaVinculo(): BelongsTo
    {
        return $this->belongsTo(DiscenteProgramaVinculo::class);
    }

    public function docenteProgramaVinculo(): BelongsTo
    {
        return $this->belongsTo(DocenteProgramaVinculo::class);
    }

    public function classificacaoIeo(): string
    {
        if ($this->ieo_final === null) return '—';

        $ieo = $this->ieo_final;
        $minCat = collect([
            $this->nota_cat1, $this->nota_cat2, $this->nota_cat3,
            $this->nota_cat4, $this->nota_cat5, $this->nota_cat6,
        ])->filter()->min() ?? 0;

        if ($ieo >= 90 && $minCat >= 75) return 'Excelente';
        if ($ieo >= 80)                  return 'Muito bom';
        if ($ieo >= 70)                  return 'Bom';
        if ($ieo >= 60)                  return 'Regular';
        return 'Crítico';
    }
}
