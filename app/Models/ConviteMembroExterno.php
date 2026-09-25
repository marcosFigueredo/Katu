<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConviteMembroExterno extends Model
{
    protected $table = 'convites_membros_externos';

    protected $fillable = [
        'programa_id',
        'criado_por',
        'user_id',
        'nome',
        'email',
        'token_hash',
        'status',
        'observacoes',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function disponivel(): bool
    {
        return in_array($this->status, ['enviado', 'cadastro_iniciado', 'devolvido', 'aguardando_validacao'], true)
            && $this->expires_at?->isFuture();
    }
}
