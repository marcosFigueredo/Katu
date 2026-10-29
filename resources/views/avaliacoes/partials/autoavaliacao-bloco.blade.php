{{-- Autoavaliação do orientador (só defesa). As respostas são privadas à CPA: aqui só o status. --}}
<h2 class="h5 fw-bold text-dark mb-3">
    <i class="bi bi-person-check me-2" style="color:#00303A;"></i>Autoavaliação
</h2>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        @if ($autoavaliacao)
            <div class="d-flex align-items-center gap-2 text-success">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="fw-semibold">Autoavaliação concluída em {{ $autoavaliacao->enviada_em->format('d/m/Y \à\s H:i') }}.</span>
            </div>
            <p class="small text-muted mb-0 mt-1">As respostas são privadas à CPA.</p>
        @elseif (! $baremaAutoavaliacao)
            <div class="alert alert-warning small mb-0">
                Barema de Autoavaliação do orientador não cadastrado. Procure a Secretaria.
            </div>
        @elseif ($banca->homologacao_status === 'homologada' && ! in_array($banca->status, ['finalizada', 'cancelada'], true))
            <p class="small text-muted mb-3">
                Como presidente da banca, responda a autoavaliação da sua atuação na orientação.
                Ela é condição para finalizar a banca e enviar a ata e a folha de aprovação aos membros.
                As respostas são privadas à CPA.
            </p>
            <a href="{{ route('avaliacoes.autoavaliacao.form', $banca) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil-square me-1"></i>Iniciar autoavaliação
            </a>
        @else
            <p class="small text-muted mb-0">A autoavaliação fica disponível com a banca homologada.</p>
        @endif
    </div>
</div>
