<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Convite PPGMSB</title></head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2 style="color:#00303A;">Convite para cadastro no PPGMSB</h2>
    <p>Olá, {{ $convite->nome }}.</p>
    <p>A Secretaria do programa {{ $convite->programa?->nome ?? 'PPGMSB' }} convidou você para realizar seu cadastro como membro externo de banca.</p>
    <p><a href="{{ $urlConvite }}">Acessar formulário de cadastro</a></p>
    <p>O link é válido até {{ $convite->expires_at?->format('d/m/Y H:i') }}. Tenha em mãos seus dados acadêmicos e o diploma de doutorado.</p>
</body>
</html>
