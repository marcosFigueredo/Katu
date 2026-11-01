<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\Barema;
use App\Models\EspelhoDiscente;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\PedidoHomologacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\Concerns\CriaDadosPesquisa;
use Tests\Concerns\CriaItensDaDefesa;
use Tests\TestCase;

class EspelhoDiscenteTest extends TestCase
{
    use CriaCenarioDeBanca;
    use CriaDadosPesquisa;
    use CriaItensDaDefesa;
    use RefreshDatabase;

    /** @return array{0: User, 1: Banca, 2: Barema, 3: User, 4: User, 5: list<User>} discente, banca, barema, secretaria, orientador, avaliadores */
    private function defesaProposta(string $tipo = 'defesa'): array
    {
        [$secretaria, , $banca, $discente, $orientador, , , $avaliadores] = $this->criarCenarioCompleto($tipo);
        $banca->update(['homologacao_status' => 'proposta', 'documento_homologacao_id' => null]);
        $barema = Barema::where('tipo', $tipo)->with('secoes.itens')->firstOrFail();

        return [$discente, $banca->fresh(), $barema, $secretaria, $orientador, $avaliadores];
    }

    /** @return array<int, int|string> */
    private function notas(Barema $barema, array $valores): array
    {
        $itens = $barema->secoes->flatMap->itens->sortBy('ordem')->values();

        return $itens->mapWithKeys(fn ($item, $i) => [$item->id => $valores[$i] ?? null])
            ->filter(fn ($v) => $v !== null)->all();
    }

    public function test_formulario_mostra_perguntas_do_barema_de_defesa_e_nao_do_sigiloso(): void
    {
        [$discente] = $this->defesaProposta();

        $this->actingAs($discente)->get(route('meu-trabalho.espelho.form'))
            ->assertOk()
            ->assertSee('Item 1 de Defesa')
            ->assertSee('Item 2 de Defesa')
            ->assertDontSee('Defesa Confidencial')
            ->assertSee('id="modal-enviar-espelho"', false)
            ->assertSee('Depois de enviadas, não podem ser alteradas.')
            ->assertDontSee('confirm(', false);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee(route('meu-trabalho.espelho.form'), false);
    }

    public function test_envio_completo_grava_notas_com_dimensao(): void
    {
        [$discente, $banca, $barema] = $this->defesaProposta();

        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [9, 7])])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('meu-trabalho.index'));

        $espelho = EspelhoDiscente::sole();
        $this->assertSame($banca->id, $espelho->banca_id);
        $this->assertSame($barema->id, $espelho->barema_id);
        $this->assertEquals(8.0, (float) $espelho->nota_final);
        $this->assertNotNull($espelho->enviada_em);
        $this->assertSame(2, $espelho->notas()->count());
        $this->assertSame(0, $espelho->notas()->whereNull('dimensao_qualidade_id')->count());
    }

    public function test_item_faltando_ou_fora_do_intervalo_e_recusado(): void
    {
        [$discente, , $barema] = $this->defesaProposta();

        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [9])])
            ->assertSessionHasErrors();
        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [9, 11])])
            ->assertSessionHasErrors();
        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [-1, 5])])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('espelhos_discente', 0);
    }

    public function test_segundo_envio_e_recusado_e_notas_originais_ficam(): void
    {
        [$discente, , $barema] = $this->defesaProposta();
        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [9, 7])]);

        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [1, 1])])
            ->assertSessionHasErrors('espelho');

        $this->assertDatabaseCount('espelhos_discente', 1);
        $this->assertEquals(8.0, (float) EspelhoDiscente::sole()->nota_final);
        $this->actingAs($discente)->get(route('meu-trabalho.espelho.form'))->assertRedirect(route('meu-trabalho.index'));
    }

    public function test_envio_simultaneo_nao_gera_erro_500(): void
    {
        [$discente, $banca, $barema] = $this->defesaProposta();

        // Simula o outro POST gravando o espelho entre a checagem e o insert deste.
        EspelhoDiscente::creating(function () use ($banca, $barema) {
            EspelhoDiscente::withoutEvents(fn () => EspelhoDiscente::create([
                'banca_id' => $banca->id, 'barema_id' => $barema->id, 'nota_final' => 8, 'enviada_em' => now(),
            ]));
        });

        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [1, 1])])
            ->assertSessionHasErrors('espelho');
        // (no teste o espelho "concorrente" cai no rollback da mesma conexão; na vida real ele já está gravado)
    }

    public function test_qualificacao_nao_pede_espelho(): void
    {
        [$discente] = $this->defesaProposta('qualificacao');

        $this->actingAs($discente)->get(route('meu-trabalho.espelho.form'))->assertNotFound();
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertDontSee(route('meu-trabalho.espelho.form'), false);
    }

    public function test_pedido_de_defesa_exige_espelho(): void
    {
        [$discente, $banca, $barema] = $this->defesaProposta();
        $this->criarDadosPesquisa($banca->trabalhoAcademico);
        $this->criarItensDaDefesa($banca->trabalhoAcademico);
        $documento = TrabalhoDocumento::firstOrFail();
        $documento->update(['tipo_documento' => 'dissertacao']);
        $servico = app(PedidoHomologacaoService::class);

        try {
            $servico->enviar($banca, $documento);
            $this->fail('O pedido de defesa sem espelho deveria ser bloqueado.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Responda o espelho da avaliação (suas notas de 0 a 10 às perguntas da banca) antes de enviar a defesa à homologação.',
                $e->errors()['homologacao'][0] ?? null,
            );
        }

        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [9, 7])]);

        try {
            $servico->enviar($banca->fresh(), $documento);
        } catch (ValidationException $e) {
            $this->assertNotContains(
                'Responda o espelho da avaliação (suas notas de 0 a 10 às perguntas da banca) antes de enviar a defesa à homologação.',
                $e->errors()['homologacao'] ?? [],
            );
        }
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_avaliador_orientador_e_secretaria_nao_acessam(): void
    {
        [$discente, , $barema, $secretaria, $orientador, $avaliadores] = $this->defesaProposta();
        $this->actingAs($discente)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [9, 7])]);

        foreach ([$secretaria, $orientador, $avaliadores[0]] as $usuario) {
            $this->actingAs($usuario)->get(route('meu-trabalho.espelho.form'))->assertForbidden();
            $this->actingAs($usuario)->post(route('meu-trabalho.espelho.store'), ['notas' => $this->notas($barema, [1, 1])])
                ->assertForbidden();
        }
        $this->assertEquals(8.0, (float) EspelhoDiscente::sole()->nota_final);
    }
}
