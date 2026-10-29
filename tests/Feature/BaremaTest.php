<?php

namespace Tests\Feature;

use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BaremaTest extends TestCase
{
    use RefreshDatabase;

    private function secComPrograma(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        DB::table('programa_usuarios')->insert([
            'programa_id'           => $programa->id,
            'user_id'               => $sec->id,
            'categoria_no_programa' => 'secretaria',
            'ativo'                 => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        return [$sec, $programa];
    }

    // ── Listagem ──────────────────────────────────────────────────────────────

    public function test_secretaria_lista_baremas(): void
    {
        [$sec, $programa] = $this->secComPrograma();
        Barema::create(['programa_id' => $programa->id, 'nome' => 'Barema Q', 'tipo' => 'qualificacao', 'nivel' => 'mestrado', 'versao' => '1.0']);

        $response = $this->actingAs($sec)->get('/baremas');

        $response->assertStatus(200)->assertSee('Barema Q');
    }

    public function test_lista_baremas_sem_programa_da_secretaria_retorna_403(): void
    {
        $sec = User::factory()->secretaria()->create();

        $response = $this->actingAs($sec)->get('/baremas');

        $response->assertStatus(403);
    }

    // ── Criação ───────────────────────────────────────────────────────────────

    public function test_secretaria_cria_barema(): void
    {
        [$sec] = $this->secComPrograma();

        $response = $this->actingAs($sec)->post('/baremas', [
            'nome'   => 'Barema Defesa 2025',
            'tipo'   => 'defesa',
            'nivel'  => 'mestrado',
            'versao' => '2.0',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('baremas', ['nome' => 'Barema Defesa 2025']);
    }

    public function test_secretaria_cria_barema_de_autoavaliacao_do_orientador(): void
    {
        [$sec] = $this->secComPrograma();

        $this->actingAs($sec)->post('/baremas', [
            'nome'   => 'Autoavaliação 2026',
            'tipo'   => 'autoavaliacao_orientador',
            'nivel'  => 'ambos',
            'versao' => '1.0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('baremas', ['nome' => 'Autoavaliação 2026', 'tipo' => 'autoavaliacao_orientador']);
        $this->actingAs($sec)->get('/baremas')->assertOk()->assertSee('Autoavaliação do orientador');
        $this->actingAs($sec)->get('/baremas/create')->assertOk()->assertSee('value="autoavaliacao_orientador"', false);
    }

    public function test_criacao_barema_falha_sem_nome(): void
    {
        [$sec] = $this->secComPrograma();

        $response = $this->actingAs($sec)->post('/baremas', [
            'tipo'   => 'defesa',
            'nivel'  => 'mestrado',
            'versao' => '2.0',
        ]);

        $response->assertSessionHasErrors('nome');
    }

    public function test_criacao_barema_falha_tipo_invalido(): void
    {
        [$sec] = $this->secComPrograma();

        $response = $this->actingAs($sec)->post('/baremas', [
            'nome'   => 'Barema X',
            'tipo'   => 'invalido',
            'nivel'  => 'mestrado',
            'versao' => '1.0',
        ]);

        $response->assertSessionHasErrors('tipo');
    }

    public function test_docente_nao_cria_barema(): void
    {
        $docente = User::factory()->docente()->create();

        $response = $this->actingAs($docente)->post('/baremas', [
            'nome'   => 'Tentativa',
            'tipo'   => 'defesa',
            'nivel'  => 'mestrado',
            'versao' => '1.0',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('baremas', ['nome' => 'Tentativa']);
    }

    // ── Edição ────────────────────────────────────────────────────────────────

    public function test_secretaria_edita_barema(): void
    {
        [$sec, $programa] = $this->secComPrograma();
        $barema = Barema::create(['programa_id' => $programa->id, 'nome' => 'Antigo', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => '1.0']);

        $response = $this->actingAs($sec)->put("/baremas/{$barema->id}", [
            'nome'   => 'Atualizado',
            'tipo'   => 'defesa',
            'nivel'  => 'mestrado',
            'versao' => '1.1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('baremas', ['nome' => 'Atualizado', 'versao' => '1.1']);
    }

    // ── Exclusão ──────────────────────────────────────────────────────────────

    public function test_secretaria_exclui_barema(): void
    {
        [$sec, $programa] = $this->secComPrograma();
        $barema = Barema::create(['programa_id' => $programa->id, 'nome' => 'Para excluir', 'tipo' => 'qualificacao', 'nivel' => 'mestrado', 'versao' => '1.0']);

        $this->actingAs($sec)->delete("/baremas/{$barema->id}")->assertRedirect();

        $this->assertDatabaseMissing('baremas', ['id' => $barema->id]);
    }

    // ── Cálculo de pontos ─────────────────────────────────────────────────────

    public function test_barema_calcula_total_pontos_corretamente(): void
    {
        [$sec, $programa] = $this->secComPrograma();
        $barema = Barema::create(['programa_id' => $programa->id, 'nome' => 'B', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => '1.0']);
        $secao  = BaremaSecao::create(['barema_id' => $barema->id, 'nome' => 'S1', 'ordem' => 1]);
        BaremaItem::create(['barema_secao_id' => $secao->id, 'nome' => 'I1', 'nota_maxima' => 10, 'peso' => 2, 'ordem' => 1]);
        BaremaItem::create(['barema_secao_id' => $secao->id, 'nome' => 'I2', 'nota_maxima' => 5,  'peso' => 1, 'ordem' => 2]);

        $barema->load('secoes.itens');

        // Total = (10 * 2) + (5 * 1) = 25
        $this->assertEquals(25.0, $barema->totalPontos());
    }
}
