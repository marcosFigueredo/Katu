<?php

namespace App\Services;

use App\Models\Banca;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pedido único do discente à homologação: a banca proposta mais o trabalho.
 * Estados de bancas.homologacao_status: proposta → aguardando → homologada | devolvida (→ aguardando).
 */
class PedidoHomologacaoService
{
    /** Estados em que o discente ainda monta/corrige o pedido. */
    public const ESTADOS_EDITAVEIS = ['proposta', 'devolvida'];

    public function __construct(
        private readonly VerificacaoBancaService $verificacao,
    ) {
    }

    /** A banca não cancelada mais recente que ainda aceita trabalho e envio à homologação. */
    public function bancaDoPedido(TrabalhoAcademico $trabalho): ?Banca
    {
        return $trabalho->bancas()
            ->where('status', '!=', 'cancelada')
            ->whereIn('homologacao_status', self::ESTADOS_EDITAVEIS)
            ->latest('id')
            ->first();
    }

    public function enviar(Banca $banca, TrabalhoDocumento $documento): void
    {
        if (! in_array($banca->homologacao_status, self::ESTADOS_EDITAVEIS, true) || $banca->status === 'cancelada') {
            throw ValidationException::withMessages([
                'homologacao' => 'Esta banca não está aberta para envio à homologação.',
            ]);
        }

        if ($documento->trabalho_academico_id !== $banca->trabalho_academico_id) {
            throw ValidationException::withMessages([
                'homologacao' => 'O documento não pertence ao trabalho desta banca.',
            ]);
        }

        $banca->loadMissing('trabalhoAcademico.discenteVinculo.discenteProfile');
        if (blank($banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->orcid_url)) {
            throw ValidationException::withMessages([
                'orcid' => 'Informe seu ORCID em Meu perfil antes de enviar o pedido à homologação.',
            ]);
        }

        $this->verificacao->exigir($this->verificacao->verificarBanca($banca), 'homologacao');

        $tentativa = (int) ($banca->homologacao_tentativa ?? 1);

        DB::transaction(function () use ($banca, $documento, $tentativa) {
            $banca->update([
                'documento_homologacao_id' => $documento->id,
                'homologacao_status' => 'aguardando',
                'homologacao_tentativa' => $tentativa + ($banca->homologacao_status === 'devolvida' ? 1 : 0),
                'libera_envio_trabalho' => false,
            ]);
        });
    }
}
