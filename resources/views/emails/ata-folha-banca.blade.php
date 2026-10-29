<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ata e folha de aprovação</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto;">

    @php
        $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        $etapa = $banca->tipo === 'defesa' ? 'defesa' : 'qualificação';
    @endphp

    <div style="background:#00303A; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">Ata e folha de aprovação</h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">
        <p>Prezado(a) <strong>{{ $membro->name }}</strong>,</p>

        <p>
            A banca de {{ $etapa }} de <strong>{{ $discente?->name ?? 'discente' }}</strong> foi finalizada pelo presidente da banca.
            Segue em anexo a folha de aprovação; a ata está disponível no sistema.
        </p>

        <p style="margin-top:20px;">
            <a href="{{ route('avaliacoes.ata', $banca) }}"
               style="background:#00303A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px; display:inline-block; font-weight:bold;">
                Abrir a ata da banca
            </a>
        </p>
    </div>
</body>
</html>
