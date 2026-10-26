<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O app usa fluxo customizado de recuperação de senha (RecuperacaoSenhaTemporariaMail).
 * A rota /forgot-password do Breeze foi substituída pela rota usuarios.recuperar-senha.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_returns_ok(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }
}
