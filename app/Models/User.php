<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'categoria',
        'primeiro_acesso',
        'cadastro_status',
        'ativo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'primeiro_acesso' => 'boolean',
            'cadastro_status' => 'string',
            'ativo' => 'boolean',
        ];
    }
    public function docenteProfile(): HasOne
    {
        return $this->hasOne(DocenteProfile::class);
    }

    public function discenteProfile(): HasOne
    {
        return $this->hasOne(DiscenteProfile::class);
    }
    public function programaUsuarios(): HasMany
{
    return $this->hasMany(ProgramaUsuario::class);
}

    /** Programa em que o usuário tem mandato vigente na CPA, ou null. */
    public function programaCpa(): ?Programa
    {
        return MembroCpa::vigentes()->where('user_id', $this->id)->with('programa')->latest('inicio_em')->first()?->programa;
    }
}
