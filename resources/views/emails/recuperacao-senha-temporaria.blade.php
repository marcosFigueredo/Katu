<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de acesso</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">

    <h2 style="color: #00303A;">
        Recuperação de acesso
    </h2>

    <p>
        Olá, <strong>{{ $usuario->name }}</strong>.
    </p>

    <p>
        Uma nova senha temporária foi gerada para seu acesso ao
        <strong>Sistema de Avaliação de Bancas do PPGMSB</strong>.
    </p>

    <p>
        <strong>E-mail de acesso:</strong> {{ $usuario->email }}<br>
        <strong>Senha temporária:</strong> {{ $senhaTemporaria }}
    </p>

    <p>
        Acesse o sistema pelo link abaixo:
    </p>

    <p>
        <a href="{{ $urlLogin }}" style="color: #00303A;">
            {{ $urlLogin }}
        </a>
    </p>

    <p>
        Ao entrar no sistema, você deverá alterar obrigatoriamente essa senha temporária.
    </p>

    <hr>

    <p style="font-size: 13px; color: #666;">
        Caso você não tenha solicitado essa recuperação, informe a secretaria do PPGMSB.
    </p>

</body>
</html>