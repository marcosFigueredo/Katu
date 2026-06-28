<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrcidWork extends Model
{
    protected $fillable = [
        'docente_profile_id',
        'orcid_id',
        'titulo',
        'ano',
        'tipo',
        'doi',
        'journal',
        'url',
        'fonte',
        'consultado_em',
    ];

    protected function casts(): array
    {
        return [
            'consultado_em' => 'datetime',
        ];
    }

    public function docenteProfile(): BelongsTo
    {
        return $this->belongsTo(DocenteProfile::class);
    }
}
