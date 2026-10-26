<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\PerguntaEstrategica;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerguntaEstrategicaTest extends TestCase
{
    use RefreshDatabase;

    private function secComPeriodo(): array
    {
        $sec      = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB']);
        DB::table('programa_usuarios')->insert([
            'programa_id'           => $programa->id,
            'user_id'               => $sec->id,
            'categoria_no_programa' => 'secretaria',
            'ativo'                 => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $periodo = AutoavaliacaoPeriodo::create([
            'programa_id' => $programa->id,
            'nome'        => 'Autoavaliação 2025',
            'ano'         => 2025,
            'data_inicio' => '2025-01-01',
            'data_fim'    => '2025-12-31',
            'status'      => 'concluido',
        ]);

        AutoavaliacaoIndicador::create([
            'autoavaliacao_periodo_id' => $periodo->id,
            'codigo'                   => 'A17',
            'nome'                     => 'Trabalhos com aderência temática confirmada por IA à linha de pesquisa (%)',
            'valor_numerico'           => 85.0,
            'calculado_em'             => now(),
        ]);

        return [$sec, $programa, $periodo];
    }

    public function test_secretaria_ve_painel_com_pergunta_verde(): void
    {
        [$sec, $programa, $periodo] = $this->secComPeriodo();

        $pergunta = PerguntaEstrategica::create([
            'programa_id' => $programa->id,
            'item_capes'  => '2.1',
            'texto'       => 'Os trabalhos possuem aderência às linhas de pesquisa?',
            'origem'      => 'capes',
            'ativa'       => true,
        ]);
        $pergunta->indicadores()->create([
            'indicador_codigo' => 'A17',
            'direcao'          => 'maior_melhor',
            'limiar_verde'     => 80,
            'limiar_amarelo'   => 50,
        ]);

        $response = $this->actingAs($sec)->get('/perguntas-estrategicas?periodo=' . $periodo->id);

        $response->assertStatus(200)
            ->assertSee('Os trabalhos possuem aderência às linhas de pesquisa?')
            ->assertSee('Atende');
    }

    public function test_docente_nao_acessa_painel(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get('/perguntas-estrategicas')->assertStatus(403);
    }

    public function test_secretaria_cria_pergunta_com_indicador(): void
    {
        [$sec] = $this->secComPeriodo();

        $response = $this->actingAs($sec)->post('/perguntas-estrategicas', [
            'item_capes'  => '2.1',
            'texto'       => 'Nova pergunta da comissão',
            'indicadores' => [
                ['codigo' => 'A18', 'direcao' => 'maior_melhor', 'limiar_verde' => 80, 'limiar_amarelo' => 50],
            ],
        ]);

        $response->assertRedirect(route('perguntas-estrategicas.index'));
        $this->assertDatabaseHas('perguntas_estrategicas', ['texto' => 'Nova pergunta da comissão', 'origem' => 'comissao']);
        $this->assertDatabaseHas('pergunta_indicadores', ['indicador_codigo' => 'A18']);
    }

    public function test_criacao_falha_sem_indicador(): void
    {
        [$sec] = $this->secComPeriodo();

        $response = $this->actingAs($sec)->post('/perguntas-estrategicas', [
            'texto' => 'Pergunta sem indicador',
        ]);

        $response->assertSessionHasErrors('indicadores');
    }

    public function test_secretaria_atualiza_pergunta(): void
    {
        [$sec, $programa] = $this->secComPeriodo();

        $pergunta = PerguntaEstrategica::create([
            'programa_id' => $programa->id,
            'texto'       => 'Texto original',
            'origem'      => 'comissao',
            'ativa'       => true,
        ]);
        $pergunta->indicadores()->create([
            'indicador_codigo' => 'A17',
            'direcao'          => 'maior_melhor',
            'limiar_verde'     => 80,
            'limiar_amarelo'   => 50,
        ]);

        $response = $this->actingAs($sec)->put("/perguntas-estrategicas/{$pergunta->id}", [
            'texto'       => 'Texto atualizado',
            'ativa'       => '1',
            'indicadores' => [
                ['codigo' => 'A09', 'direcao' => 'maior_melhor', 'limiar_verde' => 0.75, 'limiar_amarelo' => 0.5],
            ],
        ]);

        $response->assertRedirect(route('perguntas-estrategicas.index'));
        $this->assertDatabaseHas('perguntas_estrategicas', ['id' => $pergunta->id, 'texto' => 'Texto atualizado']);
        $this->assertDatabaseHas('pergunta_indicadores', ['pergunta_estrategica_id' => $pergunta->id, 'indicador_codigo' => 'A09']);
        $this->assertDatabaseMissing('pergunta_indicadores', ['pergunta_estrategica_id' => $pergunta->id, 'indicador_codigo' => 'A17']);
    }

    public function test_pergunta_capes_nao_pode_ser_excluida(): void
    {
        [$sec, $programa] = $this->secComPeriodo();

        $pergunta = PerguntaEstrategica::create([
            'programa_id' => $programa->id,
            'texto'       => 'Pergunta oficial CAPES',
            'origem'      => 'capes',
            'ativa'       => true,
        ]);

        $this->actingAs($sec)->delete("/perguntas-estrategicas/{$pergunta->id}")->assertStatus(403);
        $this->assertDatabaseHas('perguntas_estrategicas', ['id' => $pergunta->id]);
    }

    public function test_secretaria_exclui_pergunta_da_comissao(): void
    {
        [$sec, $programa] = $this->secComPeriodo();

        $pergunta = PerguntaEstrategica::create([
            'programa_id' => $programa->id,
            'texto'       => 'Pergunta da comissão',
            'origem'      => 'comissao',
            'ativa'       => true,
        ]);

        $this->actingAs($sec)->delete("/perguntas-estrategicas/{$pergunta->id}")
            ->assertRedirect(route('perguntas-estrategicas.index'));

        $this->assertDatabaseMissing('perguntas_estrategicas', ['id' => $pergunta->id]);
    }

    public function test_secretaria_nao_altera_pergunta_de_outro_programa(): void
    {
        [$sec] = $this->secComPeriodo();
        $outro = Programa::create(['nome' => 'Outro']);
        $pergunta = \App\Models\PerguntaEstrategica::create([
            'programa_id' => $outro->id, 'texto' => 'Pergunta de outro programa', 'origem' => 'comissao', 'ativa' => true,
        ]);

        $this->actingAs($sec)->get(route('perguntas-estrategicas.index'))->assertDontSee('Pergunta de outro programa');
        $this->actingAs($sec)->get(route('perguntas-estrategicas.edit', $pergunta))->assertForbidden();
        $this->actingAs($sec)->delete(route('perguntas-estrategicas.destroy', $pergunta))->assertForbidden();
        $this->assertDatabaseHas('perguntas_estrategicas', ['id' => $pergunta->id]);
    }
}
