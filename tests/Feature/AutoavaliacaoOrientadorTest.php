<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoOrientador;
use App\Models\Banca;
use App\Models\Barema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class AutoavaliacaoOrientadorTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    /** @return array{0: User, 1: Banca, 2: User, 3: User, 4: list<User>, 5: Barema} secretaria, banca, orientador, coorientador, avaliadores, barema */
    private function defesaEmAvaliacao(string $tipo = 'defesa'): array
    {
        [$secretaria, , $banca, , $orientador, $coorientador, , $avaliadores] = $this->criarCenarioCompleto($tipo);
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);
        $barema = Barema::where('tipo', 'autoavaliacao_orientador')->with('secoes.itens')->firstOrFail();

        return [$secretaria, $banca->fresh(), $orientador, $coorientador, $avaliadores, $barema];
    }

    /** @return array<int, int|string> */
    private function notas(Barema $barema, array $valores): array
    {
        $itens = $barema->secoes->flatMap->itens->sortBy('ordem')->values();

        return $itens->mapWithKeys(fn ($item, $i) => [$item->id => $valores[$i] ?? null])
            ->filter(fn ($v) => $v !== null)->all();
    }

    public function test_bloco_aparece_antes_do_status_dos_avaliadores_so_na_defesa(): void
    {
        [, $banca, $orientador] = $this->defesaEmAvaliacao();

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertSeeInOrder(['Autoavaliação', 'Iniciar autoavaliação', 'Status dos avaliadores'])
            ->assertSee(route('avaliacoes.autoavaliacao.form', $banca), false);

        [, $qualificacao, $orientadorQ] = $this->defesaEmAvaliacao('qualificacao');
        $this->actingAs($orientadorQ)->get(route('avaliacoes.banca', $qualificacao))
            ->assertOk()
            ->assertDontSee('Iniciar autoavaliação');
    }

    public function test_orientador_envia_autoavaliacao_completa(): void
    {
        [, $banca, $orientador, , , $barema] = $this->defesaEmAvaliacao();

        $this->actingAs($orientador)->get(route('avaliacoes.autoavaliacao.form', $banca))
            ->assertOk()
            ->assertSee('Item 1 de Autoavaliação do orientador');

        $this->actingAs($orientador)
            ->post(route('avaliacoes.autoavaliacao.store', $banca), ['notas' => $this->notas($barema, [8, 6]), 'observacoes' => 'Reflexão'])
            ->assertRedirect(route('avaliacoes.banca', $banca))
            ->assertSessionHas('success', 'Autoavaliação concluída.');

        $auto = AutoavaliacaoOrientador::firstOrFail();
        $this->assertSame($banca->id, $auto->banca_id);
        $this->assertEquals(7.0, (float) $auto->nota_final);
        $this->assertNotNull($auto->enviada_em);
        $this->assertSame(2, $auto->notas()->count());
    }

    public function test_item_faltando_ou_fora_do_intervalo_e_recusado(): void
    {
        [, $banca, $orientador, , , $barema] = $this->defesaEmAvaliacao();
        $ids = array_keys($this->notas($barema, [1, 1]));

        foreach ([[-1, 5], [11, 5], ['abc', 5], [5, null]] as [$a, $b]) {
            $notas = [$ids[0] => $a];
            if ($b !== null) {
                $notas[$ids[1]] = $b;
            }

            $erro = $b === null ? "notas.{$ids[1]}" : "notas.{$ids[0]}";
            $this->actingAs($orientador)
                ->post(route('avaliacoes.autoavaliacao.store', $banca), ['notas' => $notas])
                ->assertSessionHasErrors($erro);
        }

        $this->assertSame(0, AutoavaliacaoOrientador::count());
    }

    public function test_nao_reenvia(): void
    {
        [, $banca, $orientador, , , $barema] = $this->defesaEmAvaliacao();
        $this->actingAs($orientador)->post(route('avaliacoes.autoavaliacao.store', $banca), ['notas' => $this->notas($barema, [8, 6])]);

        $this->actingAs($orientador)
            ->post(route('avaliacoes.autoavaliacao.store', $banca), ['notas' => $this->notas($barema, [1, 1])])
            ->assertSessionHasErrors(['autoavaliacao' => 'A autoavaliação já foi concluída.']);

        $this->assertSame(1, AutoavaliacaoOrientador::count());
        $this->assertEquals(7.0, (float) AutoavaliacaoOrientador::first()->nota_final);
    }

    public function test_coorientador_avaliador_outro_docente_e_qualificacao_recebem_403(): void
    {
        [, $banca, , $coorientador, $avaliadores, $barema] = $this->defesaEmAvaliacao();
        $outro = User::factory()->docente()->create();
        $notas = ['notas' => $this->notas($barema, [5, 5])];

        foreach ([$coorientador, $avaliadores[0], $outro] as $usuario) {
            $this->actingAs($usuario)->get(route('avaliacoes.autoavaliacao.form', $banca))->assertForbidden();
            $this->actingAs($usuario)->post(route('avaliacoes.autoavaliacao.store', $banca), $notas)->assertForbidden();
        }

        [, $qualificacao, $orientadorQ] = $this->defesaEmAvaliacao('qualificacao');
        $this->actingAs($orientadorQ)->get(route('avaliacoes.autoavaliacao.form', $qualificacao))->assertForbidden();
        $this->actingAs($orientadorQ)->post(route('avaliacoes.autoavaliacao.store', $qualificacao), $notas)->assertForbidden();

        $this->assertSame(0, AutoavaliacaoOrientador::count());
    }

    public function test_respostas_nao_aparecem_para_ninguem(): void
    {
        [$secretaria, $banca, $orientador, , $avaliadores, $barema] = $this->defesaEmAvaliacao();
        $this->actingAs($orientador)->post(route('avaliacoes.autoavaliacao.store', $banca), [
            'notas' => $this->notas($barema, [8, 6]),
            'observacoes' => 'Observação privada da orientadora',
        ]);

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertSee('Autoavaliação concluída em')
            ->assertDontSee('Observação privada da orientadora')
            ->assertDontSee('Iniciar autoavaliação');
        $this->actingAs($orientador)->get(route('avaliacoes.autoavaliacao.form', $banca))
            ->assertRedirect(route('avaliacoes.banca', $banca));

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertDontSee('Autoavaliação concluída')
            ->assertDontSee('Observação privada da orientadora');

        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))
            ->assertOk()
            ->assertDontSee('Observação privada da orientadora');
    }

    public function test_barema_de_autoavaliacao_nao_aparece_para_avaliadores(): void
    {
        [, $banca, , , $avaliadores] = $this->defesaEmAvaliacao();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertSee('Barema Defesa')
            ->assertDontSee('Barema Autoavaliação do orientador');
    }
}
