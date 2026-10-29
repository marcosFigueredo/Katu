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

    /** "Grande área › Área › Subárea". */
    public function caminho(): string
    {
        $partes = [$this->nome];
        $atual = $this->pai;
        while ($atual) {
            array_unshift($partes, $atual->nome);
            $atual = $atual->pai;
        }

        return implode(' › ', $partes);
    }
}
