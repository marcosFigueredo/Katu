<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Período de avaliação do programa: faixas das 5 classes e pesos (SWARA) definidos pela CPA. */
class PeriodoQualidade extends Model
{
    protected $table = 'periodos_qualidade';

    public const FONTES = [
        'trabalhos' => 'Avaliação dos trabalhos (banca e produção)',
        'autoavaliacao_orientador' => 'Autoavaliação do orientador',
        'avaliacao_orientando' => 'Avaliação do orientando',
        'espelho' => 'Espelho do estudante',
    ];

    protected $fillable = [
        'programa_id', 'nome', 'inicio_em', 'fim_em',
        'corte_alta', 'corte_boa', 'corte_suficiente', 'corte_pouca', 'criado_por',
    ];

    protected $attributes = [
        'corte_alta' => 8.5,
        'corte_boa' => 7.0,
        'corte_suficiente' => 5.5,
        'corte_pouca' => 4.0,
    ];

    protected function casts(): array
    {
        return [
            'inicio_em' => 'date',
            'fim_em' => 'date',
            'corte_alta' => 'float',
            'corte_boa' => 'float',
            'corte_suficiente' => 'float',
            'corte_pouca' => 'float',
        ];
    }

    /** Chaves de cada grupo de pesos, na ordem padrão. */
    public static function chaves(string $grupo): array
    {
        return $grupo === 'fonte'
            ? array_keys(self::FONTES)
            : array_column(DimensaoQualidade::PADRAO, 'codigo');
    }

    public static function rotulos(string $grupo): array
    {
        return $grupo === 'fonte'
            ? self::FONTES
            : array_column(DimensaoQualidade::PADRAO, 'nome', 'codigo');
    }

    /** [chave => peso]; sem SWARA definido, pesos iguais. */
    public function pesos(string $grupo): array
    {
        $definidos = $this->pesosQualidade->where('grupo', $grupo)->pluck('peso', 'chave')->map(fn ($p) => (float) $p)->all();
        if ($definidos !== []) {
            return $definidos;
        }

        $chaves = self::chaves($grupo);

        return array_fill_keys($chaves, 1 / count($chaves));
    }

    /** Classe de 1 (alta) a 5 (baixa) pelas faixas do período. */
    public function classe(float $indice): int
    {
        return match (true) {
            $indice >= $this->corte_alta => 1,
            $indice >= $this->corte_boa => 2,
            $indice >= $this->corte_suficiente => 3,
            $indice >= $this->corte_pouca => 4,
            default => 5,
        };
    }

    public static function doPrograma(Programa $programa, \DateTimeInterface $data): ?self
    {
        return static::where('programa_id', $programa->id)
            ->whereDate('inicio_em', '<=', $data)
            ->whereDate('fim_em', '>=', $data)
            ->latest('inicio_em')
            ->first();
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function pesosQualidade(): HasMany
    {
        return $this->hasMany(PesoQualidade::class);
    }
}
