<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar acesso - Sistema de Avaliação de Bancas PPGMSB</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            background-color: #00303A;
        }

        .auth-page {
            height: 100vh;
            overflow: hidden;
            background-color: #00303A;
        }

        .auth-left {
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .auth-card-wrapper {
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .auth-card {
            width: 100%;
        }

        @media (max-width: 991.98px) {
            body {
                background-color: #f4f6f8;
            }

            .auth-page {
                min-height: 100vh;
                height: auto;
                overflow: auto;
                background-color: #f4f6f8;
                padding: 1rem 0;
            }

            .auth-card-wrapper {
                min-height: 100vh;
                height: auto;
                align-items: center;
            }
        }
    </style>
</head>

<body>

    <main class="auth-page">
        <div class="container h-100">
            <div class="row h-100 align-items-center">

                <div class="col-lg-7 d-none d-lg-block">
                    <div class="auth-left text-white pe-xl-5">

                        <div>
                            <img src="{{ asset('images/logoBranco.png') }}"
                                alt="PPGMSB"
                                class="img-fluid mb-4"
                                style="max-width: 260px;">

                            <hr class="border-light opacity-50 mb-4">

                            <h1 class="display-6 fw-bold mb-4">
                                Recuperação de Acesso
                            </h1>

                            <p class="lead mb-4">
                                Informe o e-mail cadastrado no Sistema de Avaliação de Bancas do PPGMSB.
                            </p>

                            <p class="mb-4 opacity-75">
                                Caso o e-mail esteja vinculado a um usuário ativo, o sistema gerará uma
                                nova senha temporária. No próximo acesso, será obrigatória a alteração
                                dessa senha.
                            </p>

                            <a href="https://www.ppgmsb.uneb.br"
                                class="btn btn-outline-light btn-lg rounded-pill px-4">
                                Retornar ao site do PPGMSB
                            </a>
                        </div>

                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="auth-card-wrapper">
                        <div class="auth-card">

                            <div class="card border-0 rounded-4 shadow-lg">
                                <div class="card-body p-4 p-md-5">

                                    <div class="mb-4 text-center text-lg-start">
                                        <h2 class="h3 fw-bold mb-2" style="color: #00303A;">
                                            Esqueci minha senha
                                        </h2>

                                        <p class="text-muted mb-0">
                                            Digite seu e-mail cadastrado para receber uma nova senha temporária.
                                        </p>
                                    </div>

                                    @if (session('status'))
                                        <div class="alert alert-success">
                                            {{ session('status') }}
                                        </div>
                                    @endif

                                    @if ($errors->any())
                                        <div class="alert alert-danger">
                                            <div class="fw-semibold mb-1">
                                                Verifique o campo informado.
                                            </div>

                                            <ul class="mb-0 ps-3">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('password.email') }}">
                                        @csrf

                                        <div class="form-floating mb-4">
                                            <input type="email"
                                                class="form-control"
                                                name="email"
                                                id="email"
                                                value="{{ old('email') }}"
                                                placeholder="nome@exemplo.com"
                                                required
                                                autofocus>

                                            <label for="email">
                                                E-mail cadastrado
                                            </label>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit"
                                                class="btn btn-lg text-white fw-semibold rounded-pill"
                                                style="background-color: #00303A;">
                                                Gerar nova senha temporária
                                            </button>
                                        </div>
                                    </form>

                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <a href="{{ route('login') }}"
                                            class="text-decoration-none"
                                            style="color: #00303A;">
                                            Voltar ao login
                                        </a>

                                        <a href="https://www.ppgmsb.uneb.br"
                                            class="text-decoration-none d-lg-none"
                                            style="color: #00303A;">
                                            Site do PPGMSB
                                        </a>
                                    </div>

                                    <div class="border-top mt-4 pt-4">
                                        <p class="small text-muted mb-0">
                                            Por segurança, a senha anterior não é recuperada. O sistema sempre
                                            gera uma nova senha temporária.
                                        </p>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

</body>
</html>