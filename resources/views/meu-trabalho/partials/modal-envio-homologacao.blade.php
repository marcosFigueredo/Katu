{{-- Confirmação do envio à homologação. Parâmetros: $idModal, $action, $oQue ("este trabalho" / "este documento"). --}}
<div class="modal fade" id="{{ $idModal }}" tabindex="-1" aria-labelledby="{{ $idModal }}-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-body p-4">
                <h2 class="h5 fw-bold mb-2" id="{{ $idModal }}-titulo">
                    <i class="bi bi-send-check me-1" style="color:#00303A;"></i>Enviar à homologação?
                </h2>
                <p class="mb-2">A banca proposta e {{ $oQue }} seguem para a análise da Secretaria.</p>
                <p class="small text-muted mb-0">Enquanto o pedido estiver na fila, não será possível alterá-lo.</p>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="{{ $action }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-success fw-semibold">
                        <i class="bi bi-send-fill me-1"></i>Confirmar envio
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
