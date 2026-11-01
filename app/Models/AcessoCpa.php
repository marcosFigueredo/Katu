<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de acesso de um membro da CPA (auditoria do conflito de interesse). */
class AcessoCpa extends Model
{
    protected $table = 'acessos_cpa';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'programa_id', 'pagina', 'filtros'];

    protected function casts(): array
    {
        return ['filtros' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
