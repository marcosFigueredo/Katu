{{-- Bolsas do discente: a Secretaria registra a partir dos documentos oficiais, a qualquer tempo. --}}
@use('App\Models\Bolsa')
<div class="card border-0 shadow-sm mt-4" id="bolsas">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-3" style="color:#00303A;">
            <i class="bi bi-cash-coin me-1"></i>Bolsas
        </h2>

        @forelse ($bolsasDiscente as $bolsa)
            <div class="border rounded p-2 mb-2">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="fw-semibold">{{ $bolsa->agenciaNome }}</span> · {{ Bolsa::MODALIDADES[$bolsa->modalidade] ?? $bolsa->modalidade }}
                        @if ($bolsa->numero_processo) · {{ $bolsa->numero_processo }} @endif
                        <div class="small text-muted">
                            {{ $bolsa->inicio_em?->format('d/m/Y') }} a {{ $bolsa->fim_em?->format('d/m/Y') ?? 'em andamento' }}
                            · registrada por {{ $bolsa->registradoPor?->name ?? '—' }} em {{ $bolsa->created_at?->format('d/m/Y') }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('usuarios.bolsas.destroy', [$usuario, $bolsa]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remover</button>
                    </form>
                </div>
                <details class="mt-2">
                    <summary class="small">Corrigir</summary>
                    <form method="POST" action="{{ route('usuarios.bolsas.update', [$usuario, $bolsa]) }}" class="mt-2">
                        @csrf
                        @method('PUT')
                        @include('usuarios.partials.bolsa-campos', ['bolsa' => $bolsa, 'idBase' => 'bolsa'.$bolsa->id])
                        <button class="btn btn-sm btn-outline-primary mt-2">Salvar</button>
                    </form>
                </details>
            </div>
        @empty
            <p class="text-muted small">Nenhuma bolsa registrada.</p>
        @endforelse

        <h3 class="h6 fw-bold mt-3">Registrar bolsa</h3>
        <form method="POST" action="{{ route('usuarios.bolsas.store', $usuario) }}">
            @csrf
            @include('usuarios.partials.bolsa-campos', ['bolsa' => null, 'idBase' => 'nova_bolsa'])
            <button class="btn btn-sm btn-primary mt-2">Registrar bolsa</button>
        </form>
    </div>
</div>
