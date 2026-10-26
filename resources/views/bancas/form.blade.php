<x-app-layout>
    <div class="container-fluid">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ $modoDiscente ? route('meu-trabalho.index') : route('bancas.index') }}" class="text-decoration-none">
                        {{ $modoDiscente ? 'Meu Trabalho' : 'Bancas' }}
                    </a>
                </li>
                <li class="breadcrumb-item active">
                    {{ $banca->exists ? ($modoDiscente ? 'Editar proposta' : 'Gerenciar banca') : ($modoDiscente ? 'Propor banca' : 'Nova banca') }}
                </li>
            </ol>
        </nav>

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color: #00303A;">
                {{ $modoDiscente ? ($banca->exists ? 'Editar Proposta de Banca' : 'Propor Banca') : ($banca->exists ? 'Gerenciar Banca' : 'Cadastrar Banca') }}
            </h1>
            <p class="text-muted mb-0">
                @if ($modoDiscente)
                    Proponha a banca, envie o trabalho em Meu Trabalho e clique em "Enviar à homologação". A Secretaria homologa o pedido e só então os avaliadores recebem o trabalho.
                @else
                    O orientador preside a banca, coorientadores aparecem em ata e apenas avaliadores lançam nota.
                @endif
            </p>
        </div>

        @if ($modoDiscente && $banca->exists && $banca->homologacao_status === 'devolvida' && $banca->homologacao_observacoes)
            <div class="alert alert-danger">
                <strong>Motivo da devolução:</strong> {{ $banca->homologacao_observacoes }}
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-mortarboard-fill me-2" style="color:#00303A;"></i>Trabalho
                        </h6>
                    </div>
                    <div class="card-body">
                        @if ($trabalho)
                            @php
                                $discente = $trabalho->discenteVinculo?->discenteProfile?->user;
                                $vinculo = $trabalho->discenteVinculo;
                                $orientador = $trabalho->orientacoes->firstWhere('tipo', 'orientador');
                                $coorientadores = $trabalho->orientacoes->where('tipo', 'coorientador');
                            @endphp
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted fw-normal">Discente</dt>
                                <dd class="col-7 fw-semibold mb-2">{{ $discente?->name ?? '-' }}</dd>

                                <dt class="col-5 text-muted fw-normal">Nível</dt>
                                <dd class="col-7 mb-2">
                                    {{ match($trabalho->nivel) {
                                        'mestrado' => 'Mestrado',
                                        'doutorado' => 'Doutorado',
                                        default => $trabalho->nivel ?? '-',
                                    } }}
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Linha</dt>
                                <dd class="col-7 mb-2">{{ $vinculo?->linhaPesquisa?->nome ?? '-' }}</dd>

                                <dt class="col-5 text-muted fw-normal">Presidente</dt>
                                <dd class="col-7 mb-2">
                                    {{ $orientador?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '-' }}
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Coorientadores</dt>
                                <dd class="col-7 mb-0">
                                    @forelse ($coorientadores as $coorientador)
                                        <div>{{ $coorientador->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '-' }}</div>
                                    @empty
                                        -
                                    @endforelse
                                </dd>
                            </dl>
                        @else
                            <p class="text-muted small mb-0">
                                Selecione um trabalho no formulário para carregar os avaliadores disponíveis.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="alert alert-info small">
                    <div class="fw-semibold mb-1">Regras do curso</div>
                    @if ($regra)
                        <div>{{ $regra['min_avaliadores'] === $regra['max_avaliadores'] ? $regra['min_avaliadores'] : $regra['min_avaliadores'].' a '.$regra['max_avaliadores'] }} avaliadores.</div>
                        <div>Obrigatório: ≥ {{ $regra['min_internos'] }} interno(s) ao {{ $siglaPrograma }} e ≥ {{ $regra['min_externos_instituicao'] }} externo(s) à {{ $siglaInstituicao }}.</div>
                    @else
                        <div>Selecione o trabalho para ver as regras do curso do discente.</div>
                    @endif
                    <div>O discente precisa ter ORCID informado.</div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-person-workspace me-2" style="color:#00303A;"></i>Composição
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="banca-form" method="POST" action="{{ $banca->exists ? route('bancas.update', $banca) : route('bancas.store') }}">
                            @csrf
                            @if ($banca->exists)
                                @method('PUT')
                            @endif
                            <input type="hidden" name="enviar_email_participantes" id="enviar_email_participantes" value="0">

                            @if ($banca->exists || $modoDiscente)
                                <input type="hidden" name="trabalho_academico_id" value="{{ $trabalho->id }}">
                            @else
                                <div class="mb-3">
                                    <label for="trabalho_academico_id" class="form-label fw-semibold">Discente / Trabalho</label>
                                    <select id="trabalho_academico_id"
                                            name="trabalho_academico_id"
                                            class="form-select @error('trabalho_academico_id') is-invalid @enderror"
                                            onchange="if (this.value) window.location='{{ route('bancas.create') }}?trabalho_id=' + this.value">
                                        <option value="">Selecione...</option>
                                        @foreach ($trabalhos as $item)
                                            <option value="{{ $item->id }}"
                                                {{ old('trabalho_academico_id', $trabalho?->id) == $item->id ? 'selected' : '' }}>
                                                {{ $item->discenteVinculo?->discenteProfile?->user?->name ?? 'Discente sem nome' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="row g-3 mb-3">
                                <div class="{{ $modoDiscente ? 'col-md-6' : 'col-md-4' }}">
                                    <label for="tipo" class="form-label fw-semibold">Tipo</label>
                                    <select id="tipo" name="tipo" class="form-select">
                                        <option value="qualificacao" {{ old('tipo', $banca->tipo) === 'qualificacao' ? 'selected' : '' }}>Qualificação</option>
                                        <option value="defesa" {{ old('tipo', $banca->tipo) === 'defesa' ? 'selected' : '' }}>Defesa</option>
                                    </select>
                                </div>
                                @if ($modoDiscente)
                                    <input type="hidden" name="status" value="cadastrada">
                                @else
                                    <div class="col-md-4">
                                        <label for="status" class="form-label fw-semibold">Status</label>
                                        <select id="status" name="status" class="form-select">
                                            <option value="cadastrada" {{ old('status', $banca->status) === 'cadastrada' ? 'selected' : '' }}>Cadastrada</option>
                                            <option value="em_avaliacao" {{ old('status', $banca->status) === 'em_avaliacao' ? 'selected' : '' }}>Em avaliação</option>
                                            <option value="finalizada" {{ old('status', $banca->status) === 'finalizada' ? 'selected' : '' }}>Finalizada</option>
                                            <option value="cancelada" {{ old('status', $banca->status) === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                                        </select>
                                    </div>
                                @endif
                                <div class="{{ $modoDiscente ? 'col-md-3' : 'col-md-2' }}">
                                    <label for="data_banca" class="form-label fw-semibold">Data</label>
                                    <input type="text"
                                           id="data_banca"
                                           name="data_banca"
                                           class="form-control @error('data_banca') is-invalid @enderror"
                                           placeholder="DD/MM/AAAA"
                                           inputmode="numeric"
                                           maxlength="10"
                                           autocomplete="off"
                                           value="{{ old('data_banca', $banca->data_hora?->format('d/m/Y')) }}">
                                    @error('data_banca')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div id="data_banca_feedback" class="invalid-feedback">
                                        A data não pode ser anterior a hoje.
                                    </div>
                                </div>
                                <div class="{{ $modoDiscente ? 'col-md-3' : 'col-md-2' }}">
                                    <label for="horario_banca" class="form-label fw-semibold">Horário</label>
                                    <input type="text"
                                           id="horario_banca"
                                           name="horario_banca"
                                           class="form-control @error('horario_banca') is-invalid @enderror"
                                           placeholder="HH:MM"
                                           inputmode="numeric"
                                           maxlength="5"
                                           autocomplete="off"
                                           value="{{ old('horario_banca', $banca->data_hora?->format('H:i')) }}">
                                    @error('horario_banca')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="local" class="form-label fw-semibold">Local / sala / link</label>
                                <input type="text"
                                       id="local"
                                       name="local"
                                       class="form-control"
                                       value="{{ old('local', $banca->local) }}">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Avaliadores</label>

                                @if (! $trabalho)
                                    <div class="alert alert-warning small mb-0">Selecione um trabalho para listar avaliadores.</div>
                                @elseif ($avaliadores->isEmpty())
                                    <div class="alert alert-warning small mb-0">
                                        Nenhum docente avaliador disponível. Verifique se há docentes ativos com permissão de avaliação, além do orientador/coorientadores.
                                    </div>
                                @else
                                    @php
                                        $quantidadeSelecionada = (int) old('quantidade_avaliadores', max($regra['min_avaliadores'] ?? 2, $avaliadoresAtuais->count(), 1));
                                        $adicionaisSelecionados = old('avaliadores_adicionais', $avaliadoresAdicionaisAtuais);
                                    @endphp

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="quantidade_avaliadores" class="form-label small fw-semibold">
                                                Quantos avaliadores?
                                            </label>
                                            <select id="quantidade_avaliadores"
                                                    name="quantidade_avaliadores"
                                                    class="form-select @error('quantidade_avaliadores') is-invalid @enderror">
                                                @for ($qtd = 1; $qtd <= 5; $qtd++)
                                                    <option value="{{ $qtd }}" {{ $quantidadeSelecionada === $qtd ? 'selected' : '' }}>
                                                        {{ $qtd }} {{ $qtd === 1 ? 'avaliador' : 'avaliadores' }}
                                                    </option>
                                                @endfor
                                            </select>
                                            @error('quantidade_avaliadores')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-8">
                                            <div class="alert alert-light border small mb-0">
                                                A banca terá <strong><span id="resumo_quantidade">{{ $quantidadeSelecionada }}</span> avaliadores</strong>.
                                                A composição mínima segue as regras do curso indicadas acima.
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="avaliador_interno_id" class="form-label small fw-semibold">
                                                Avaliador interno ao {{ $siglaPrograma }}
                                            </label>
                                            <select id="avaliador_interno_id"
                                                    name="avaliador_interno_id"
                                                    class="form-select @error('avaliador_interno_id') is-invalid @enderror">
                                                <option value="">Selecione...</option>
                                                @foreach ($internos as $avaliador)
                                                    <option value="{{ $avaliador->id }}"
                                                        {{ old('avaliador_interno_id', $primeiroInterno) == $avaliador->id ? 'selected' : '' }}>
                                                        {{ $avaliador->docenteProfile?->user?->name ?? '-' }}
                                                        {{ $avaliador->docenteProfile?->instituicao_sigla ? ' - '.$avaliador->docenteProfile->instituicao_sigla : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('avaliador_interno_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="avaliador_externo_id" class="form-label small fw-semibold">
                                                Avaliador externo à {{ $siglaInstituicao }}
                                            </label>
                                            <select id="avaliador_externo_id"
                                                    name="avaliador_externo_id"
                                                    class="form-select @error('avaliador_externo_id') is-invalid @enderror">
                                                <option value="">Selecione...</option>
                                                @foreach ($externosUneb as $avaliador)
                                                    <option value="{{ $avaliador->id }}"
                                                        {{ old('avaliador_externo_id', $primeiroExterno) == $avaliador->id ? 'selected' : '' }}>
                                                        {{ $avaliador->docenteProfile?->user?->name ?? '-' }}
                                                        {{ $avaliador->docenteProfile?->instituicao_sigla ? ' - '.$avaliador->docenteProfile->instituicao_sigla : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('avaliador_externo_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        @for ($slot = 0; $slot < 3; $slot++)
                                            <div class="col-md-6 avaliador-adicional" data-slot="{{ $slot + 3 }}">
                                                <label for="avaliador_adicional_{{ $slot }}" class="form-label small fw-semibold">
                                                    Avaliador adicional {{ $slot + 3 }}
                                                </label>
                                                <select id="avaliador_adicional_{{ $slot }}"
                                                        name="avaliadores_adicionais[]"
                                                        class="form-select @error('avaliadores_adicionais.'.$slot) is-invalid @enderror">
                                                    <option value="">Selecione...</option>
                                                    @foreach ($avaliadores as $avaliador)
                                                        @php
                                                            $profile = $avaliador->docenteProfile;
                                                            $externoUneb = $avaliador->tipo_vinculo === 'externo'
                                                                || (($profile?->instituicao_sigla ?? '') !== '' && strtoupper(trim($profile->instituicao_sigla)) !== strtoupper(trim($siglaInstituicao)));
                                                        @endphp
                                                        <option value="{{ $avaliador->id }}"
                                                            {{ ($adicionaisSelecionados[$slot] ?? null) == $avaliador->id ? 'selected' : '' }}>
                                                            {{ $profile?->user?->name ?? '-' }}
                                                            - {{ $avaliador->tipo_vinculo === 'interno' ? 'Interno ' . $siglaPrograma : 'Externo' }}
                                                            {{ $externoUneb ? ' / externo ' . $siglaInstituicao : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('avaliadores_adicionais.'.$slot)
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        @endfor
                                    </div>
                                    <div class="form-text">
                                        Orientador e coorientadores não aparecem nas listas porque não avaliam.
                                    </div>
                                @endif
                            </div>

                            <div class="mb-4">
                                <label for="observacoes" class="form-label fw-semibold">Observações</label>
                                <textarea id="observacoes" name="observacoes" rows="3" class="form-control">{{ old('observacoes', $banca->observacoes) }}</textarea>
                            </div>

                            <div class="d-flex gap-2 pt-2 border-top">
                                @if ($modoDiscente)
                                    <button type="submit"
                                            class="btn btn-primary fw-semibold"
                                            {{ ! $trabalho ? 'disabled' : '' }}>
                                        <i class="bi bi-floppy-fill me-2"></i>
                                        {{ $banca->exists ? 'Salvar proposta' : 'Propor banca' }}
                                    </button>
                                @else
                                    <button type="button"
                                            class="btn btn-primary fw-semibold"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-email-participantes"
                                            {{ ! $trabalho ? 'disabled' : '' }}>
                                        <i class="bi bi-floppy-fill me-2"></i>
                                        {{ $banca->exists ? 'Atualizar banca' : 'Cadastrar banca' }}
                                    </button>
                                @endif
                                <a href="{{ $modoDiscente ? route('meu-trabalho.index') : route('bancas.index') }}" class="btn btn-outline-secondary">Cancelar</a>

                                @if ($banca->exists && ! $modoDiscente)
                                    <button type="submit"
                                            form="delete-banca"
                                            class="btn btn-outline-danger ms-auto"
                                            onclick="return confirm('Excluir esta banca?')">
                                        Excluir
                                    </button>
                                @endif
                            </div>
                        </form>

                        @if ($banca->exists && ! $modoDiscente)
                            <form id="delete-banca" method="POST" action="{{ route('bancas.destroy', $banca) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($banca->exists)
            @if ($indicadores)
                @include('bancas._indicadores', compact('indicadores'))
            @else
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-hourglass-split me-2"></i>
                            O índice de composição da banca está sendo calculado em segundo plano.
                            Recarregue a página em instantes para visualizá-lo.
                        </div>
                    </div>
                </div>
            @endif
        @endif

    </div>

    {{-- Modal: horário fora do padrão — apenas aviso, não bloqueia --}}
    <div class="modal fade" id="modalHorarioIncomum" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold" style="color:#856404;">
                        <i class="bi bi-clock-history me-2"></i>Horário incomum
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small pb-2">
                    O horário <strong id="horarioIncomumValor"></strong> está fora do intervalo usual
                    de bancas (07:00 – 17:59). Você pode mantê-lo se for intencional.
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-warning btn-sm fw-semibold" data-bs-dismiss="modal">
                        Entendido, manter horário
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-email-participantes" tabindex="-1" aria-labelledby="modal-email-participantes-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-email-participantes-label">Envia email aos participantes?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Se confirmar, o sistema enviará um e-mail para o discente, orientador, coorientadores e avaliadores com os dados da banca.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="salvar-sem-email">
                        Não
                    </button>
                    <button type="button" class="btn btn-primary" id="salvar-com-email">
                        Sim
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // ── Máscara data DD/MM/AAAA ──────────────────────────────────────
            const dataBanca = document.getElementById('data_banca');
            const dataFeedback = document.getElementById('data_banca_feedback');

            if (dataBanca) {
                dataBanca.addEventListener('input', function () {
                    let v = this.value.replace(/\D/g, '').substring(0, 8);
                    let r = '';
                    for (let i = 0; i < v.length; i++) {
                        if (i === 2 || i === 4) r += '/';
                        r += v[i];
                    }
                    this.value = r;
                });

                dataBanca.addEventListener('blur', function () {
                    const val = this.value;
                    if (val.length < 10) return;
                    const [d, m, y] = val.split('/').map(Number);
                    const entered = new Date(y, m - 1, d);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const invalid = isNaN(entered.getTime()) || entered < today;
                    this.classList.toggle('is-invalid', invalid);
                    if (dataFeedback) dataFeedback.style.display = invalid ? 'block' : 'none';
                });

                // Impede submissão se data inválida
                dataBanca.closest('form')?.addEventListener('submit', function (e) {
                    const val = dataBanca.value;
                    if (val.length === 10) {
                        const [d, m, y] = val.split('/').map(Number);
                        const entered = new Date(y, m - 1, d);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        if (isNaN(entered.getTime()) || entered < today) {
                            e.preventDefault();
                            dataBanca.classList.add('is-invalid');
                            if (dataFeedback) dataFeedback.style.display = 'block';
                            dataBanca.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                }, true);
            }

            // ── Máscara hora HH:MM + aviso horário incomum ───────────────────
            const horarioBanca = document.getElementById('horario_banca');
            const modalIncomum = document.getElementById('modalHorarioIncomum');
            const horarioValorSpan = document.getElementById('horarioIncomumValor');

            if (horarioBanca) {
                horarioBanca.addEventListener('input', function () {
                    let v = this.value.replace(/\D/g, '').substring(0, 4);
                    if (v.length > 2) v = v.substring(0, 2) + ':' + v.substring(2);
                    this.value = v;
                });

                horarioBanca.addEventListener('blur', function () {
                    const val = this.value;
                    if (val.length < 5) return;
                    const hour = parseInt(val.split(':')[0], 10);
                    const min  = parseInt(val.split(':')[1], 10);
                    if (hour > 23 || min > 59) return;
                    const incomum = hour >= 18 || hour < 7;
                    if (incomum && modalIncomum) {
                        if (horarioValorSpan) horarioValorSpan.textContent = val;
                        bootstrap.Modal.getOrCreateInstance(modalIncomum).show();
                    }
                });
            }

            // ── Avaliadores adicionais ────────────────────────────────────────
            const quantidade = document.getElementById('quantidade_avaliadores');
            const resumo = document.getElementById('resumo_quantidade');
            const adicionais = Array.from(document.querySelectorAll('.avaliador-adicional'));
            const form = document.getElementById('banca-form');
            const enviarEmail = document.getElementById('enviar_email_participantes');
            const salvarSemEmail = document.getElementById('salvar-sem-email');
            const salvarComEmail = document.getElementById('salvar-com-email');

            function atualizarAvaliadoresAdicionais() {
                const total = Number(quantidade?.value || 2);

                if (resumo) {
                    resumo.textContent = total;
                }

                adicionais.forEach((campo) => {
                    const slot = Number(campo.dataset.slot);
                    const visivel = slot <= total;
                    campo.classList.toggle('d-none', !visivel);

                    const select = campo.querySelector('select');
                    if (select) {
                        select.disabled = !visivel;
                        if (!visivel) {
                            select.value = '';
                        }
                    }
                });
            }

            quantidade?.addEventListener('change', atualizarAvaliadoresAdicionais);
            atualizarAvaliadoresAdicionais();

            salvarSemEmail?.addEventListener('click', () => {
                enviarEmail.value = '0';
                form.submit();
            });

            salvarComEmail?.addEventListener('click', () => {
                enviarEmail.value = '1';
                form.submit();
            });
        });
    </script>
</x-app-layout>
