<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Banca voltou para revisão</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 640px; margin: 0 auto;">

    @php
        $discente = $banca->trabalhoAcademico?->discenteVinculo?->discenteProfile?->user;
        $etapa = $banca->tipo === 'defesa' ? 'defesa' : 'qualificação';
    @endphp

    <div style="background:#b45309; padding:24px 32px; border-radius:8px 8px 0 0;">
        <h1 style="color:#fff; font-size:20px; margin:0;">Banca voltou para revisão</h1>
    </div>

    <div style="background:#f9fafb; padding:24px 32px; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">

        <p>Prezado(a) <strong>{{ $avaliador->name }}</strong>,</p>

        <p>
            O orientador de <strong>{{ $discente?->name ?? 'discente' }}</strong> foi trocado pela Secretaria.
            Por isso, a banca de {{ $etapa }} da qual você faz parte voltou para revisão da composição
            e precisará ser homologada de novo.
        </p>

        <p>
            Aguarde a nova homologação antes de avaliar o trabalho. Se a sua participação for mantida,
            você receberá um novo aviso.
        </p>
    </div>
</body>
</html>
