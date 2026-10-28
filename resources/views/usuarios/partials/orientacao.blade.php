{{-- Bloco "Orientação" na edição do discente: vigentes, troca auditável e linha do tempo. --}}
@php
    $historico = $trabalhoOrientacao->orientacoesHistorico;
    $vigentes = $historico->where('ativo', true);
    $orientadorVigente = $vigentes->firstWhere('tipo', 'orientador');
    $coorientadoresVigentes = $vigentes->where('tipo', 'coorientador')->sortBy('ordem');
    $maxCoorientadores = (int) ($trabalhoOrientacao->programa?->max_coorientadores ?? 1);
    $idsVigentes = $vigentes->pluck('docente_programa_vinculo_id');
    $podemOrientar = $docentesOrientacao->where('pode_orientar', true);
    $nomeDocente = fn ($vinculo) => $vinculo?->docenteProfile?->user?->name ?? '—';
@endphp

<div class="card border-0 shadow-sm mt-4" id="orientacao">
    <div class="card-body">
        <h2 class="h5 fw-bold mb-3" style="color:#00303A;">
            <i class="bi bi-people me-1"></i>Orientação
        </h2>

        {{-- Orientador vigente --}}
        <div class="mb-4">
            <div class="small text-muted mb-1">Orientador</div>
            @if ($orientadorVigente)
                <div class="fw-semibold">{{ $nomeDocente($orientadorVigente->docenteProgramaVinculo) }}</div>
                <div class="small text-muted">desde {{ $orientadorVigente->inicio_em?->format('d/m/Y') }}</div>
            @else
                <div class="alert alert-warning py-2 mb-2">Sem orientador</div>
                <form method="POST" action="{{ route('usuarios.orientacao.definir', $usuario) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label for="orientador_inicial" class="form-label small">Orientador</label>
                        <select name="orientador_vinculo_id" id="orientador_inicial" class="form-select" required>
                            <option value="">Selecione…</option>
                            @foreach ($podemOrientar as $vinculo)
                                <option value="{{ $vinculo->id }}" @selected(old('orientador_vinculo_id') == $vinculo->id)>{{ $nomeDocente($vinculo) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Registrar orientador</button>
                    </div>
                </form>
            @endif
            @error('orientador_vinculo_id')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        {{-- Troca de orientador --}}
        @if ($orientadorVigente)
            <details class="mb-4" @if ($errors->hasAny(['processo_sei', 'justificativa'])) open @endif>
                <summary class="fw-semibold">Trocar orientador</summary>
                <form method="POST" action="{{ route('usuarios.orientacao.trocar', $usuario) }}" class="row g-2 mt-2">
                    @csrf
                    <div class="col-md-6">
                        <label for="novo_orientador" class="form-label small">Novo orientador</label>
                        <select name="orientador_vinculo_id" id="novo_orientador" class="form-select" required>
                            <option value="">Selecione…</option>
                            @foreach ($podemOrientar->where('id', '!=', $orientadorVigente->docente_programa_vinculo_id) as $vinculo)
                                <option value="{{ $vinculo->id }}" @selected(old('orientador_vinculo_id') == $vinculo->id)>{{ $nomeDocente($vinculo) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="processo_sei" class="form-label small">Processo SEI</label>
                        <input type="text" name="processo_sei" id="processo_sei" maxlength="50" class="form-control" value="{{ old('processo_sei') }}" required>
                        @error('processo_sei') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Data</label>
                        <div class="form-control-plaintext small">hoje, automática</div>
                    </div>
                    <div class="col-12">
                        <label for="justificativa" class="form-label small">Justificativa</label>
                        <textarea name="justificativa" id="justificativa" rows="2" maxlength="2000" class="form-control" required>{{ old('justificativa') }}</textarea>
                        @error('justificativa') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Confirmar troca</button>
                    </div>
                </form>
            </details>
        @endif

        {{-- Coorientadores vigentes --}}
        <div class="mb-4">
            <div class="small text-muted mb-1">Coorientadores ({{ $coorientadoresVigentes->count() }} de {{ $maxCoorientadores }})</div>
            @forelse ($coorientadoresVigentes as $co)
                <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2 mb-2">
                    <span>{{ $nomeDocente($co->docenteProgramaVinculo) }}
                        <span class="small text-muted">desde {{ $co->inicio_em?->format('d/m/Y') }}</span></span>
                    <form method="POST" action="{{ route('usuarios.orientacao.coorientador.retirar', [$usuario, $co]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Retirar</button>
                    </form>
                </div>
            @empty
                <div class="small text-muted">Nenhum coorientador.</div>
            @endforelse

            @if ($coorientadoresVigentes->count() < $maxCoorientadores)
                <form method="POST" action="{{ route('usuarios.orientacao.coorientador.incluir', $usuario) }}" class="row g-2 align-items-end mt-1">
                    @csrf
                    <div class="col-md-8">
                        <label for="coorientador" class="form-label small">Incluir coorientador</label>
                        <select name="coorientador_vinculo_id" id="coorientador" class="form-select" required>
                            <option value="">Selecione…</option>
                            @foreach ($docentesOrientacao->whereNotIn('id', $idsVigentes) as $vinculo)
                                <option value="{{ $vinculo->id }}">{{ $nomeDocente($vinculo) }}{{ $vinculo->tipo_vinculo === 'externo' ? ' (externo)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary w-100">Incluir</button>
                    </div>
                </form>
            @endif
            @error('coorientador_vinculo_id')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        {{-- Linha do tempo --}}
        <div>
            <div class="small text-muted mb-2">Histórico</div>
            @if ($historico->isEmpty())
                <div class="small text-muted">Nenhum registro.</div>
            @else
                <ul class="list-unstyled border-start ps-3 mb-0">
                    @foreach ($historico as $periodo)
                        <li class="mb-2">
                            <span class="badge text-bg-{{ $periodo->ativo ? 'success' : 'secondary' }}">{{ $periodo->tipo === 'orientador' ? 'Orientador' : 'Coorientador' }}</span>
                            <span class="fw-semibold">{{ $nomeDocente($periodo->docenteProgramaVinculo) }}</span>
                            <span class="small text-muted">
                                {{ $periodo->inicio_em?->format('d/m/Y') }} – {{ $periodo->fim_em?->format('d/m/Y') ?? 'atual' }}
                            </span>
                            @if ($periodo->processo_sei)
                                <div class="small">Processo SEI {{ $periodo->processo_sei }}</div>
                            @endif
                            @if ($periodo->justificativa)
                                <div class="small text-muted">{{ $periodo->justificativa }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
