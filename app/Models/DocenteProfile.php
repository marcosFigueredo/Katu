<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocenteProfile extends Model
{
    protected $fillable = [
        'user_id',
        'titulacao',
        'area_formacao',
        'lattes_url',
        'orcid_url',
        'instituicao_origem',
        'telefone',
        'telefone_whatsapp',
        'instituicao_sigla',
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
        return $this->hasMany(DocenteProgramaVinculo::class);
    }

    public function orcidSnapshot(): HasOne
    {
        return $this->hasOne(OrcidSnapshot::class);
    }

    public function orcidWorks(): HasMany
    {
        return $this->hasMany(OrcidWork::class);
    }
}
