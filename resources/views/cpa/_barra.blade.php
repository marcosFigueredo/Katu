{{-- Barra de 0 a 10 de uma dimensão. --}}
@if ($nota === null)
    <span class="small text-muted">—</span>
@else
    <div class="d-flex align-items-center gap-2">
        <div class="progress flex-grow-1" style="height: 8px; min-width: 60px;" role="progressbar" aria-valuenow="{{ $nota }}" aria-valuemin="0" aria-valuemax="10">
            <div class="progress-bar {{ $nota < 4 ? 'bg-danger' : ($nota < 7 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $nota * 10 }}%"></div>
        </div>
        <span class="small" style="width: 2.5rem;">{{ number_format($nota, 1, ',', '') }}</span>
    </div>
@endif
