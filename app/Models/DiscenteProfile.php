<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscenteProfile extends Model
{
    protected $fillable = [
        'user_id',
        'formacao',
        'instituicao_formacao',
        'lattes_url',
        'orcid_url',
        'telefone',
        'telefone_whatsapp',
        'cidade',
        'estado',
        'pais',
        'lattes_verificado',
        'orcid_verificado',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'telefone_whatsapp' => 'boolean',
            'lattes_verificado' => 'boolean',
            'orcid_verificado' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vinculosPrograma(): HasMany
    {
        return $this->hasMany(DiscenteProgramaVinculo::class);
    }
}