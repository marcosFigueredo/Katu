<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

/** Bolsa do estudante no programa (um estudante pode ter mais de uma ao longo do curso). */
class Bolsa extends Model
{
    public const AGENCIAS = [
        'capes' => 'CAPES',
        'cnpq' => 'CNPq',
        'fapesb' => 'FAPESB',
        'institucional' => 'Institucional',
        'outra' => 'Outra',
    ];

    public const MODALIDADES = [
        'mestrado' => 'Mestrado',
        'doutorado' => 'Doutorado',
        'doutorado_sanduiche' => 'Doutorado-sanduíche',
    ];

    protected $fillable = [
        'discente_programa_vinculo_id', 'agencia', 'agencia_outra', 'modalidade', 'numero_processo',
        'inicio_em', 'fim_em', 'registrado_por',
    ];

    protected function casts(): array
    {
        return ['inicio_em' => 'date', 'fim_em' => 'date'];
    }

    /** Regras de validação; com prefixo (ex.: "bolsa_") para o formulário de cadastro do usuário. */
    public static function regras(string $prefixo = ''): array
    {
        return [
            "{$prefixo}agencia" => ['required', Rule::in(array_keys(self::AGENCIAS))],
            "{$prefixo}agencia_outra" => ["required_if:{$prefixo}agencia,outra", 'nullable', 'string', 'max:150'],
            "{$prefixo}modalidade" => ['required', Rule::in(array_keys(self::MODALIDADES))],
            "{$prefixo}numero_processo" => ['nullable', 'string', 'max:100'],
            "{$prefixo}inicio_em" => ['required', 'date'],
            "{$prefixo}fim_em" => ['nullable', 'date', "after_or_equal:{$prefixo}inicio_em"],
        ];
    }

    public static function mensagens(string $prefixo = ''): array
    {
        return [
            "{$prefixo}agencia_outra.required_if" => 'Informe o nome da agência.',
            "{$prefixo}fim_em.after_or_equal" => 'O fim da bolsa não pode ser anterior ao início.',
        ];
    }

    public function getAgenciaNomeAttribute(): string
    {
        return $this->agencia === 'outra' && $this->agencia_outra ? $this->agencia_outra : (self::AGENCIAS[$this->agencia] ?? $this->agencia);
    }

    public function discenteProgramaVinculo(): BelongsTo
    {
        return $this->belongsTo(DiscenteProgramaVinculo::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
