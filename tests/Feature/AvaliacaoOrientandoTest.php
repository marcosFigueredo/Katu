<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoOrientador;
use App\Models\AvaliacaoOrientando;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\DimensaoQualidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class AvaliacaoOrientandoTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_orientador_avalia_o_orientando_na_defesa(): void
    {
        [, $banca, $orientador] = $this->cenario();

        $this->actingAs($orientador)->get(route('avaliacao-orientando.form', $banca))
            ->assertOk()
            ->assertSee('Domínio do tema')
            ->assertSee('Domínio do método')
            ->assertSee('Capacidade crítica')
            ->assertSee('Independência nas decisões')
            ->assertSee('Amadurecimento');

        $this->actingAs($orientador)->post(route('avaliacao-orientando.store', $banca), ['notas' => $this->notas(8)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('avaliacoes.banca', $banca));

        $avaliacao = AvaliacaoOrientando::sole();
        $this->assertSame(5, $avaliacao->notas()->count());
        $autonomia = DimensaoQualidade::doCodigo('autonomia')->id;
        $this->assertSame(0, $avaliacao->notas()->where('dimensao_qualidade_id', '!=', $autonomia)->count());

        $this->actingAs($orientador)->post(route('avaliacao-orientando.store', $banca), ['notas' => $this->notas(2)])
            ->assertSessionHasErrors('avaliacao_orientando');
        $this->assertEquals(8.0, (float) $avaliacao->notas()->first()->nota);
    }

    public function test_nota_fora_do_intervalo_ou_faltando_e_recusada(): void
    {
        [, $banca, $orientador] = $this->cenario();
        $notas = $this->notas(8);

        $this->actingAs($orientador)->post(route('avaliacao-orientando.store', $banca), ['notas' => ['dominio_tema' => 11] + $notas])
            ->assertSessionHasErrors('notas.dominio_tema');
        unset($notas['amadurecimento']);
        $this->actingAs($orientador)->post(route('avaliacao-orientando.store', $banca), ['notas' => $notas])
            ->assertSessionHasErrors('notas.amadurecimento');

        $this->assertDatabaseCount('avaliacoes_orientando', 0);
    }

    public function test_so_orientador_e_so_na_defesa(): void
    {
        [$secretaria, $banca, , $avaliadores, $discente] = $this->cenario();

        foreach ([$avaliadores[0], $discente, $secretaria] as $usuario) {
            $this->actingAs($usuario)->get(route('avaliacao-orientando.form', $banca))->assertForbidden();
        }

        [, $qualificacao, $orientadorQ] = $this->cenario('qualificacao');
        $this->actingAs($orientadorQ)->get(route('avaliacao-orientando.form', $qualificacao))->assertNotFound();
    }

    public function test_finalizar_defesa_exige_avaliacao_do_orientando(): void
    {
        [, $banca, $orientador] = $this->cenario(enviar: true);
        AutoavaliacaoOrientador::create([
            'banca_id' => $banca->id,
            'docente_programa_vinculo_id' => $orientador->docenteProfile->vinculosPrograma()->first()->id,
            'barema_id' => Barema::where('tipo', 'autoavaliacao_orientador')->value('id'),
            'nota_final' => 9, 'enviada_em' => now(),
        ]);

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasErrors(['finalizar' => 'Conclua a avaliação do orientando para finalizar a banca e liberar a ata e a folha de aprovação.']);
        $this->actingAs($orientador)->get(route('avaliacoes.ata', $banca))->assertForbidden();

        $this->actingAs($orientador)->post(route('avaliacao-orientando.store', $banca), ['notas' => $this->notas(8)]);
        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasNoErrors();
        $this->assertSame('finalizada', $banca->fresh()->status);
    }

    public function test_ninguem_ve_as_notas(): void
    {
        [$secretaria, $banca, $orientador, $avaliadores] = $this->cenario();
        $this->actingAs($orientador)->post(route('avaliacao-orientando.store', $banca), [
            'notas' => $this->notas(7), 'observacoes' => 'Observação privada do orientador.',
        ]);

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertSee('Avaliação do orientando concluída')
            ->assertDontSee('Observação privada do orientador.');
        $this->actingAs($avaliadores[0])->get(route('avaliacoes.banca', $banca))
            ->assertDontSee('Observação privada do orientador.');
        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))
            ->assertDontSee('Observação privada do orientador.');
        $this->actingAs($orientador)->get(route('avaliacao-orientando.form', $banca))
            ->assertRedirect(route('avaliacoes.banca', $banca));
    }

    private function notas(float $valor): array
    {
        return array_fill_keys(array_keys(AvaliacaoOrientando::ELEMENTOS), $valor);
    }

    /** Banca homologada em avaliação; com $enviar, todas as avaliações enviadas. */
    private function cenario(string $tipo = 'defesa', bool $enviar = false): array
    {
        [$secretaria, , $banca, $discente, $orientador, , , $avaliadores] = $this->criarCenarioCompleto($tipo);
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);
        if ($enviar) {
            foreach ($banca->avaliadores()->get() as $membro) {
                foreach (Barema::whereIn('tipo', [$tipo, "{$tipo}_confidencial"])->get() as $barema) {
                    BancaAvaliacao::create([
                        'banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id,
                        'status' => 'enviada', 'nota_final' => 8, 'enviada_em' => now(),
                    ]);
                }
            }
        }

        return [$secretaria, $banca->fresh(), $orientador, $avaliadores, $discente];
    }
}
