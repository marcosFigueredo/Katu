<x-app-layout>
    <div class="container-fluid">

        {{-- Cabeçalho de boas-vindas --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #00303A;">
                    Painel da Secretaria
                </h1>
                <p class="text-muted mb-0">
                    Gerencie os módulos do Katu — Autoavaliação e Qualidade de TCC · PPGMSB/UNEB.
                </p>
            </div>
            <div class="text-muted small text-end d-none d-md-block">
                <i class="bi bi-calendar3 me-1"></i>
                {{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Cards de estatísticas --}}
        <div class="row g-3 mb-5">

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: rgba(25,135,84,.12);">
                            <i class="bi bi-mortarboard-fill fs-3 text-success"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold mb-0 lh-1">{{ $totalDiscentes }}</div>
                            <div class="text-muted small mt-1">Discentes ativos</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: rgba(13,110,253,.12);">
                            <i class="bi bi-person-badge-fill fs-3 text-primary"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold mb-0 lh-1">{{ $totalDocentes }}</div>
                            <div class="text-muted small mt-1">Docentes ativos</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: rgba(255,165,0,.12);">
                            <i class="bi bi-building-fill fs-3" style="color:#fd7e14;"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold mb-0 lh-1">{{ $totalProgramas }}</div>
                            <div class="text-muted small mt-1">Programa(s) cadastrado(s)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: rgba(111,66,193,.12);">
                            <i class="bi bi-people-fill fs-3 text-purple" style="color:#6f42c1;"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold mb-0 lh-1">{{ $totalUsuarios }}</div>
                            <div class="text-muted small mt-1">Total de usuários</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Divisor de módulos --}}
        <h2 class="h6 fw-bold text-uppercase text-muted mb-3 ls-1">
            <i class="bi bi-grid-3x3-gap me-2"></i>Módulos
        </h2>

        {{-- Cards de módulos --}}
        <div class="row g-4">

            {{-- Membros externos --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('membros-externos.index') }}" class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3"><div class="rounded-3 p-3" style="background:rgba(0,48,58,.10);"><i class="bi bi-person-vcard-fill fs-3" style="color:#00303A;"></i></div><span class="badge text-bg-success">Ativo</span></div>
                        <h5 class="fw-bold mb-1 text-dark">Membros externos</h5>
                        <p class="text-muted small mb-0">Envie convites, receba diplomas e valide os membros externos das bancas.</p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3"><span class="small fw-semibold" style="color:#00303A;">Acessar <i class="bi bi-arrow-right ms-1"></i></span></div>
                </a>
            </div>

            {{-- Critérios CPA --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('criterios-cpa.index') }}" class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4"><div class="d-flex align-items-start justify-content-between mb-3"><div class="rounded-3 p-3" style="background:rgba(0,48,58,.10);"><i class="bi bi-list-check fs-3" style="color:#00303A;"></i></div><span class="badge text-bg-success">Ativo</span></div><h5 class="fw-bold mb-1 text-dark">Critérios da CPA</h5><p class="text-muted small mb-0">Configure os requisitos e evidências obrigatórias de cada etapa.</p></div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3"><span class="small fw-semibold" style="color:#00303A;">Acessar <i class="bi bi-arrow-right ms-1"></i></span></div>
                </a>
            </div>

            {{-- Instituição --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('instituicao.edit') }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 p-3" style="background: rgba(0,48,58,.10);">
                                <i class="bi bi-bank2 fs-3" style="color:#00303A;"></i>
                            </div>
                            <span class="badge text-bg-success">Ativo</span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark">Instituição</h5>
                        <p class="text-muted small mb-0">
                            Dados institucionais, sigla, endereço e informações gerais do programa.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3">
                        <span class="small fw-semibold" style="color:#00303A;">
                            Acessar <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </a>
            </div>

            {{-- Programas --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('programas.index') }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 p-3" style="background: rgba(0,48,58,.10);">
                                <i class="bi bi-diagram-3-fill fs-3" style="color:#00303A;"></i>
                            </div>
                            <span class="badge text-bg-success">Ativo</span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark">Programas, Áreas e Linhas</h5>
                        <p class="text-muted small mb-0">
                            Programas de pós-graduação, áreas de concentração e linhas de pesquisa vinculadas.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3">
                        <span class="small fw-semibold" style="color:#00303A;">
                            Acessar <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </a>
            </div>

            {{-- Usuários --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('usuarios.index') }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 p-3" style="background: rgba(0,48,58,.10);">
                                <i class="bi bi-people-fill fs-3" style="color:#00303A;"></i>
                            </div>
                            <span class="badge text-bg-success">Ativo</span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark">Usuários</h5>
                        <p class="text-muted small mb-0">
                            Cadastro e gestão de secretaria, docentes e discentes do programa.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3">
                        <span class="small fw-semibold" style="color:#00303A;">
                            Acessar <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </a>
            </div>

            {{-- Orientações --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('orientacoes.index') }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 p-3" style="background: rgba(0,48,58,.10);">
                                <i class="bi bi-arrow-left-right fs-3" style="color:#00303A;"></i>
                            </div>
                            <span class="badge text-bg-success">Ativo</span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark">Orientações</h5>
                        <p class="text-muted small mb-0">
                            Vínculo entre discentes, orientadores e coorientadores do programa.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3">
                        <span class="small fw-semibold" style="color:#00303A;">
                            Acessar <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </a>
            </div>

            {{-- Baremas --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('baremas.index') }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 p-3" style="background: rgba(0,48,58,.10);">
                                <i class="bi bi-clipboard2-check-fill fs-3" style="color:#00303A;"></i>
                            </div>
                            <span class="badge text-bg-success">Ativo</span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark">Baremas</h5>
                        <p class="text-muted small mb-0">
                            Instrumentos e critérios de avaliação aplicados às bancas examinadoras.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3">
                        <span class="small fw-semibold" style="color:#00303A;">
                            Acessar <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </a>
            </div>

            {{-- Bancas --}}
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('bancas.index') }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none module-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 p-3" style="background: rgba(0,48,58,.10);">
                                <i class="bi bi-person-workspace fs-3" style="color:#00303A;"></i>
                            </div>
                            <span class="badge text-bg-success">Ativo</span>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark">Bancas</h5>
                        <p class="text-muted small mb-0">
                            Criação, acompanhamento, avaliação e apuração das bancas examinadoras.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3">
                        <span class="small fw-semibold" style="color:#00303A;">
                            Acessar <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </a>
            </div>

        </div>

    </div>
</x-app-layout>

<style>
.module-card {
    transition: transform .15s ease, box-shadow .15s ease;
}
.module-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.10) !important;
}
</style>
