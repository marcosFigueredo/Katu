{{-- Intervalo de confiança de 95% (informativo à CPA) e aviso de fronteira entre classes. --}}
@if (! empty($intervalo))
    <div class="small text-muted" title="Intervalo de confiança de 95% por bootstrap; informativo.">
        IC 95%: {{ number_format($intervalo[0], 2, ',', '') }}–{{ number_format($intervalo[1], 2, ',', '') }}
    </div>
@endif
@if (! empty($fronteira))
    <span class="badge bg-warning-subtle text-warning-emphasis">fronteira com {{ \App\Services\IndiceQualidadeService::CLASSES[$fronteira] }}</span>
@endif
