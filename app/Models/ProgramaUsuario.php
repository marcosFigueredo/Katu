<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaUsuario extends Model
{
    protected $fillable = [
        'programa_id',
        'user_id',
        'categoria_no_programa',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}