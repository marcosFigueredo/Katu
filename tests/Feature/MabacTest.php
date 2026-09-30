<?php

namespace Tests\Feature;

use App\Models\Barema;
use App\Models\MabacAnalise;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MabacTest extends TestCase
{
    use RefreshDatabase;

    private function secComBarema(): array
    {
        $sec     = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPGMSB']);
        app(\App\Services\ContextoSecretaria::class)->vincular($sec, $programa);
        $barema  = Barema::create(['programa_id' => $programa->id, 'nome' => 'B', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => '1.0', 'ativo' => true]);
        return [$sec, $barema];
    }

    // ── Controle de acesso ────────────────────────────────────────────────────

    public function test_secretaria_acessa_indice_mabac(): void
    {
        [$sec] = $this->secComBarema();

        $this->actingAs($sec)->get('/mabac')->assertStatus(200);
    }

    public function test_docente_nao_acessa_mabac(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get('/mabac')->assertStatus(403);
    }

    public function test_discente_nao_acessa_mabac(): void
    {
        $discente = User::factory()->discente()->create();

        $this->actingAs($discente)->get('/mabac')->assertStatus(403);
    }

    // ── Criação de análise ────────────────────────────────────────────────────

    public function test_secretaria_cria_analise_mabac(): void
    {
        [$sec, $barema] = $this->secComBarema();

        $response = $this->actingAs($sec)->post('/mabac', [
            'nome'           => 'Análise 2025.1',
            'barema_id'      => $barema->id,
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim'    => '2025-06-30',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mabac_analises', ['nome' => 'Análise 2025.1']);
    }

    public function test_criacao_analise_falha_sem_barema(): void
    {
        [$sec] = $this->secComBarema();

        $response = $this->actingAs($sec)->post('/mabac', [
            'nome'           => 'Análise Inválida',
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim'    => '2025-06-30',
        ]);

        $response->assertSessionHasErrors('barema_id');
    }

    public function test_criacao_analise_falha_data_fim_antes_inicio(): void
    {
        [$sec, $barema] = $this->secComBarema();

        $response = $this->actingAs($sec)->post('/mabac', [
            'nome'           => 'Data inválida',
            'barema_id'      => $barema->id,
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-06-30',
            'periodo_fim'    => '2025-01-01',
        ]);

        $response->assertSessionHasErrors('periodo_fim');
    }

    public function test_docente_nao_cria_analise(): void
    {
        [, $barema] = $this->secComBarema();
        $docente = User::factory()->docente()->create();

        $response = $this->actingAs($docente)->post('/mabac', [
            'nome'           => 'Tentativa docente',
            'barema_id'      => $barema->id,
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim'    => '2025-06-30',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('mabac_analises', ['nome' => 'Tentativa docente']);
    }

    // ── Exibição ──────────────────────────────────────────────────────────────

    public function test_secretaria_ve_analise_existente(): void
    {
        [$sec, $barema] = $this->secComBarema();
        $analise = MabacAnalise::create([
            'barema_id'      => $barema->id,
            'nome'           => 'Análise Visível',
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim'    => '2025-06-30',
            'criado_por'     => $sec->name,
        ]);

        $this->actingAs($sec)->get("/mabac/{$analise->id}")->assertStatus(200)->assertSee('Análise Visível');
    }

    // ── Exclusão ─────────────────────────────────────────────────────────────

    public function test_secretaria_exclui_analise(): void
    {
        [$sec, $barema] = $this->secComBarema();
        $analise = MabacAnalise::create([
            'barema_id'      => $barema->id,
            'nome'           => 'Para excluir',
            'tipo_banca'     => 'defesa',
            'periodo_inicio' => '2025-01-01',
            'periodo_fim'    => '2025-06-30',
            'criado_por'     => $sec->name,
        ]);

        $this->actingAs($sec)->delete("/mabac/{$analise->id}")->assertRedirect('/mabac');

        $this->assertDatabaseMissing('mabac_analises', ['id' => $analise->id]);
    }
}
