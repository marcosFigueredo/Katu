<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Envio do projeto LaTeX (.zip) pelo discente e o resultado do processamento em fila. */
class TrabalhoSubmissaoLatex extends Model
{
    protected $table = 'trabalho_submissoes_latex';

    public const STATUS = ['processando', 'ok', 'nao_ok', 'erro'];

    protected $fillable = [
        'trabalho_academico_id',
        'banca_id',
        'banca_composicao_hash',
        'enviado_por_id',
        'tipo_documento',
        'idioma',
        'zip_path',
        'zip_hash_sha256',
        'tex_principal',
        'pdf_banca_path',
        'pdf_conferencia_path',
        'bbl_path',
        'motor',
        'status',
        'elementos',
        'conformidade',
        'log_resumido',
        'avisos',
        'numeracao_linhas',
        'refs_status',
        'refs_total',
        'refs_com_ano',
        'refs_recentes_pct',
        'refs_atualizadas',
        'refs_orientador',
    ];

    protected function casts(): array
    {
        return [
            'elementos' => 'array',
            'conformidade' => 'array',
            'avisos' => 'array',
            'numeracao_linhas' => 'boolean',
            'refs_recentes_pct' => 'decimal:2',
            'refs_atualizadas' => 'boolean',
        ];
    }

    public function trabalhoAcademico(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAcademico::class);
    }

    public function banca(): BelongsTo
    {
        return $this->belongsTo(Banca::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }

    /** Documento criado no envio à homologação. */
    public function documento(): HasOne
    {
        return $this->hasOne(TrabalhoDocumento::class, 'submissao_latex_id');
    }

    /** É a submissão mais recente do trabalho? Só ela pode seguir à homologação. */
    public function ehVigente(): bool
    {
        return ! static::where('trabalho_academico_id', $this->trabalho_academico_id)
            ->where('id', '>', $this->id)
            ->exists();
    }

    /**
     * Impressão digital da composição da banca conferida: avaliadores ativos (ids dos
     * vínculos, ordenados), data/hora e local. Muda quando o discente edita a banca.
     */
    public static function impressaoBanca(?Banca $banca): ?string
    {
        if ($banca === null) {
            return null;
        }
        $ids = $banca->avaliadores()->pluck('docente_programa_vinculo_id')
            ->map(fn ($id) => (int) $id)->sort()->values()->all();
        $banca->refresh();

        return hash('sha256', json_encode([
            'avaliadores' => $ids,
            'data_hora' => $banca->data_hora?->format('Y-m-d H:i'),
            'local' => trim((string) $banca->local),
        ]));
    }

    public function diretorioArquivos(): string
    {
        return "submissoes-latex/{$this->id}";
    }
}
