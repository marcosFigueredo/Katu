<x-app-layout>
    <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1" style="color:#00303A;">Base de Conhecimento (IA)</h1>
            <p class="text-muted mb-0">
                {{ $programa->nome }} — envie o manual de Normas e Procedimentos e o Template para habilitar a
                análise de conformidade do manuscrito por IA. O Template é opcional.
            </p>
        </div>

        <div class="row g-4">
            @foreach ([
                'normas' => ['titulo' => 'Normas e Procedimentos', 'obrigatorio' => true],
                'template' => ['titulo' => 'Template', 'obrigatorio' => false],
            ] as $tipo => $info)
                @php $doc = $documentos->get($tipo); @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="h6 fw-bold mb-0">
                                    {{ $info['titulo'] }}
                                    @if ($info['obrigatorio'])
                                        <span class="badge text-bg-secondary ms-1">obrigatório</span>
                                    @endif
                                </h2>
                                @php
                                    $statusCor = match($doc?->status_indexacao) {
                                        'concluido' => 'success',
                                        'processando', 'pendente' => 'warning',
                                        'falhou' => 'danger',
                                        default => 'secondary',
                                    };
                                    $statusLabel = match($doc?->status_indexacao) {
                                        'concluido' => 'Indexado',
                                        'processando' => 'Indexando…',
                                        'pendente' => 'Pendente',
                                        'falhou' => 'Falhou',
                                        default => 'Não enviado',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $statusCor }}">{{ $statusLabel }}</span>
                            </div>

                            @if ($doc)
                                <p class="small text-muted mb-1">{{ $doc->arquivo_nome_original }}</p>
                                @if ($doc->status_indexacao === 'concluido')
                                    <p class="small text-muted mb-2">Indexado em {{ $doc->indexado_em?->format('d/m/Y H:i') }}</p>
                                @elseif ($doc->status_indexacao === 'falhou')
                                    <p class="small text-danger mb-2">{{ $doc->erro_indexacao }}</p>
                                @endif
                            @else
                                <p class="small text-muted mb-2">Nenhum arquivo enviado ainda.</p>
                            @endif

                            <form method="POST" action="{{ route('programa-documentos-referencia.store') }}" enctype="multipart/form-data" class="mt-2">
                                @csrf
                                <input type="hidden" name="tipo" value="{{ $tipo }}">
                                <div class="input-group">
                                    <input type="file" name="arquivo" accept="application/pdf" class="form-control" required>
                                    <button type="submit" class="btn btn-primary">
                                        {{ $doc ? 'Substituir' : 'Enviar' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
