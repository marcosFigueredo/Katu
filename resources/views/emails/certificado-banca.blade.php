<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Certificado de participação</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto;">

    @php
        $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        $etapa = $banca->tipo === 'defesa' ? 'defesa' : 'qualificação';
    @endphp

    <div style="background:#00303A; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">Certificado de participação</h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">
        <p>Prezado(a) <strong>{{ $participante->name }}</strong>,</p>

        <p>
            Agradecemos sua participação na banca de {{ $etapa }} de <strong>{{ $discente?->name ?? 'discente' }}</strong>.
            Segue em anexo o certificado de participação. Ele também pode ser baixado no sistema, na página da banca.
        </p>

        <p style="font-size:12px; color:#6b7280; margin-top:24px;">Mensagem automática do Katu — não responda.</p>
    </div>
</body>
</html>
