<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cadastro de membro externo</title><link href="{{ asset('build/assets/app.css') }}" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:900px;"><div class="card border-0 shadow-sm"><div class="card-body p-4 p-md-5">
    <h1 class="h3 fw-bold" style="color:#00303A;">Cadastro de membro externo</h1>
    <p class="text-muted">Convite para {{ $convite->programa?->nome ?? 'PPGMSB' }}. E-mail: <strong>{{ $convite->email }}</strong></p>
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('membros-externos.registro.store', ['token' => $token]) }}" enctype="multipart/form-data"><input type="hidden" name="_token" value="{{ csrf_token() }}">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Nome completo</label><input class="form-control" name="name" value="{{ old('name', $convite->nome) }}" required></div>
            <div class="col-md-4"><label class="form-label">Titulação</label><input class="form-control" name="titulacao" value="{{ old('titulacao') }}" placeholder="Doutorado" required></div>
            <div class="col-md-6"><label class="form-label">Área de formação</label><input class="form-control" name="area_formacao" value="{{ old('area_formacao') }}"></div>
            <div class="col-md-6"><label class="form-label">Instituição de origem</label><input class="form-control" name="instituicao_origem" value="{{ old('instituicao_origem') }}" required></div>
            <div class="col-md-3"><label class="form-label">Sigla</label><input class="form-control" name="instituicao_sigla" value="{{ old('instituicao_sigla') }}" required></div>
            <div class="col-md-5"><label class="form-label">ORCID</label><input class="form-control" name="orcid_url" value="{{ old('orcid_url') }}" placeholder="https://orcid.org/0000-0000-0000-0000"></div>
            <div class="col-md-4"><label class="form-label">Telefone</label><input class="form-control" name="telefone" value="{{ old('telefone') }}"></div>
            <div class="col-md-6"><label class="form-label">Lattes</label><input class="form-control" name="lattes_url" value="{{ old('lattes_url') }}"></div>
            <div class="col-md-6"><label class="form-label">Diploma de doutorado</label><input type="file" class="form-control" name="diploma" accept=".jpg,.jpeg,.png,.pdf" required><div class="form-text">JPG, PNG ou PDF; máximo de 10 MB.</div></div>
        </div>
        <button class="btn btn-primary mt-4">Enviar cadastro para validação</button>
    </form>
</div></div></main></body></html>
