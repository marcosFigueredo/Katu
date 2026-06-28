<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Banca cadastrada</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    @php
        $trabalho = $banca->trabalhoAcademico;
        $discente = $trabalho?->discenteVinculo?->discenteProfile?->user;
        $orientador = $trabalho?->orientacoes?->firstWhere('tipo', 'orientador');
        $coorientadores = $trabalho?->orientacoes?->where('tipo', 'coorientador') ?? collect();
        $avaliadores = $banca->membros?->where('papel', 'avaliador') ?? collect();
        $tipo = match($banca->tipo) {
            'qualificacao' => 'Qualificação',
            'defesa' => 'Defesa',
            default => ucfirst($banca->tipo),
        };
    @endphp

    <h2 style="color: #00303A;">Banca cadastrada</h2>

    <p>
        A secretaria do PPGMSB cadastrou uma banca no Sistema de Avaliação de Bancas.
    </p>

    <p>
        <strong>Discente:</strong> {{ $discente?->name ?? '-' }}<br>
        <strong>Tipo:</strong> {{ $tipo }}<br>
        <strong>Status:</strong> Cadastrada<br>
        <strong>Data:</strong> {{ $banca->data_hora?->format('d/m/Y') ?? 'A definir' }}<br>
        <strong>Horário:</strong> {{ $banca->data_hora?->format('H:i') ?? 'A definir' }}<br>
        <strong>Local / sala / link:</strong> {{ $banca->local ?: 'A definir' }}
    </p>

    <p>
        <strong>Presidente da banca:</strong>
        {{ $orientador?->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '-' }}
    </p>

    @if ($coorientadores->isNotEmpty())
        <p>
            <strong>Coorientador(es):</strong><br>
            @foreach ($coorientadores as $coorientador)
                {{ $coorientador->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '-' }}<br>
            @endforeach
        </p>
    @endif

    <p>
        <strong>Avaliador(es):</strong><br>
        @foreach ($avaliadores as $membro)
            {{ $membro->docenteProgramaVinculo?->docenteProfile?->user?->name ?? '-' }}<br>
        @endforeach
    </p>

    @if ($banca->observacoes)
        <p>
            <strong>Observações:</strong><br>
            {{ $banca->observacoes }}
        </p>
    @endif

    <p>
        Acesse o sistema em:<br>
        <a href="{{ $urlSistema }}">{{ $urlSistema }}</a>
    </p>
</body>
</html>
