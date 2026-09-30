<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que cada papel de usuário só acessa as rotas que lhe cabem.
 */
class AcessoControleTest extends TestCase
{
    use RefreshDatabase;

    // ── Visitante não autenticado ─────────────────────────────────────────────

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $rotas = ['/baremas', '/bancas', '/avaliacoes', '/usuarios', '/mabac'];

        foreach ($rotas as $rota) {
            $this->get($rota)->assertRedirect('/login');
        }
    }

    // ── Secretaria ────────────────────────────────────────────────────────────

    public function test_secretaria_acessa_baremas(): void
    {
        $sec = $this->secretariaComPrograma();

        $this->actingAs($sec)->get('/baremas')->assertStatus(200);
    }

    public function test_secretaria_acessa_bancas(): void
    {
        $sec = $this->secretariaComPrograma();

        $this->actingAs($sec)->get('/bancas')->assertStatus(200);
    }

    public function test_secretaria_acessa_usuarios(): void
    {
        $sec = $this->secretariaComPrograma();

        $this->actingAs($sec)->get('/usuarios')->assertStatus(200);
    }

    public function test_secretaria_acessa_mabac(): void
    {
        $sec = $this->secretariaComPrograma();

        $this->actingAs($sec)->get('/mabac')->assertStatus(200);
    }

    // ── Docente ───────────────────────────────────────────────────────────────

    public function test_docente_nao_acessa_baremas(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get('/baremas')->assertStatus(403);
    }

    public function test_docente_nao_acessa_usuarios(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get('/usuarios')->assertStatus(403);
    }

    public function test_docente_nao_acessa_mabac(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get('/mabac')->assertStatus(403);
    }

    public function test_docente_acessa_avaliacoes(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get('/avaliacoes')->assertStatus(200);
    }

    public function test_pagina_autenticada_nao_e_armazenada_em_cache(): void
    {
        $docente = User::factory()->docente()->create();

        $response = $this->actingAs($docente)
            ->get('/avaliacoes')
            ->assertStatus(200);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    // ── Discente ──────────────────────────────────────────────────────────────

    public function test_discente_nao_acessa_baremas(): void
    {
        $discente = User::factory()->discente()->create();

        $this->actingAs($discente)->get('/baremas')->assertStatus(403);
    }

    public function test_discente_nao_acessa_bancas(): void
    {
        $discente = User::factory()->discente()->create();

        $this->actingAs($discente)->get('/bancas')->assertStatus(403);
    }

    public function test_discente_nao_acessa_mabac(): void
    {
        $discente = User::factory()->discente()->create();

        $this->actingAs($discente)->get('/mabac')->assertStatus(403);
    }

    public function test_discente_acessa_meu_trabalho(): void
    {
        $discente = User::factory()->discente()->create();

        $this->actingAs($discente)->get('/meu-trabalho')->assertStatus(200);
    }

    private function secretariaComPrograma(): User
    {
        $sec = User::factory()->secretaria()->create();
        app(\App\Services\ContextoSecretaria::class)->vincular($sec, \App\Models\Programa::create(['nome' => 'PPG Teste']));

        return $sec;
    }
}
