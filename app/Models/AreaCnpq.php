<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabela de Áreas do Conhecimento do CNPq (até subárea), única para o sistema. */
class AreaCnpq extends Model
{
    protected $table = 'areas_cnpq';

    public const NIVEIS = [
        'grande_area' => 'Grande área',
        'area' => 'Área',
        'subarea' => 'Subárea',
    ];

    protected $fillable = ['codigo', 'nome', 'nivel', 'pai_id', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pai_id');
    }

    /** Nível exigido do pai de cada nível. */
    public const NIVEL_DO_PAI = [
        'grande_area' => null,
        'area' => 'grande_area',
        'subarea' => 'area',
    ];

    /** "Grande área › Área › Subárea". Para ao reencontrar um item (proteção contra ciclo). */
    public function caminho(): string
    {
        $partes = [$this->nome];
        $vistos = [$this->id => true];
        $atual = $this->pai;
        while ($atual && ! isset($vistos[$atual->id])) {
            $vistos[$atual->id] = true;
            array_unshift($partes, $atual->nome);
            $atual = $atual->pai;
        }

        return implode(' › ', $partes);
    }
}
