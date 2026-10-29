<?php

namespace Tests\Feature;

use App\Mail\AtaFolhaBancaMail;
use App\Models\AutoavaliacaoOrientador;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class FinalizarBancaAtaFolhaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private const AVISO = 'Conclua sua autoavaliação para finalizar a banca e liberar a ata e a folha de aprovação.';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** Banca homologada, em avaliação, com todas as avaliações (padrão e confidencial) enviadas. */
    private function bancaProntaParaFinalizar(string $tipo = 'defesa', bool $comBaremas = true): array
    {
        [$secretaria, $programa, $banca, , $orientador, , , $avaliadores] = $this->criarCenarioCompleto($tipo, $comBaremas);
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);

        foreach ($banca->avaliadores()->get() as $membro) {
            foreach (Barema::whereIn('tipo', [$tipo, "{$tipo}_confidencial"])->get() as $barema) {
                BancaAvaliacao::create([
                    'banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id,
                    'status' => 'enviada', 'nota_final' => 8, 'enviada_em' => now(),
                ]);
            }
        }

        return [$secretaria, $programa, $banca->fresh(), $orientador, $avaliadores];
    }

    private function concluirAutoavaliacao(Banca $banca, User $orientador): void
    {
        $vinculo = $orientador->docenteProfile->vinculosPrograma()->first();
        AutoavaliacaoOrientador::create([
            'banca_id' => $banca->id, 'docente_programa_vinculo_id' => $vinculo->id,
            'barema_id' => Barema::where('tipo', 'autoavaliacao_orientador')->value('id'),
            'nota_final' => 9, 'enviada_em' => now(),
        ]);
    }

    private function finalizar(User $orientador, Banca $banca)
    {
        return $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado']);
    }

    public function test_defesa_sem_autoavaliacao_nao_finaliza(): void
    {
        [, , $banca, $orientador] = $this->bancaProntaParaFinalizar();

        $this->finalizar($orientador, $banca)->assertSessionHasErrors(['finalizar' => self::AVISO]);

        $this->assertSame('em_avaliacao', $banca->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_defesa_com_autoavaliacao_finaliza_e_envia_ata_e_folha(): void
    {
        [, , $banca, $orientador, $avaliadores] = $this->bancaProntaParaFinalizar();
        $this->concluirAutoavaliacao($banca, $orientador);

        $this->finalizar($orientador, $banca)->assertSessionHasNoErrors();

        $banca->refresh();
        $this->assertSame('finalizada', $banca->status);
        $this->assertNotNull($banca->ata_enviada_em);
        Mail::assertSent(AtaFolhaBancaMail::class, 2);
        foreach ($avaliadores as $avaliador) {
            Mail::assertSent(AtaFolhaBancaMail::class, fn (AtaFolhaBancaMail $m) => $m->hasTo($avaliador->email)
                && count($m->attachments()) === 1
                && $m->envelope()->subject === 'Ata e folha de aprovação — Banca de Defesa');
        }
    }

    public function test_qualificacao_finaliza_e_envia_sem_autoavaliacao(): void
    {
        [, , $banca, $orientador] = $this->bancaProntaParaFinalizar('qualificacao');

        $this->finalizar($orientador, $banca)->assertSessionHasNoErrors();

        $this->assertSame('finalizada', $banca->fresh()->status);
        Mail::assertSent(AtaFolhaBancaMail::class, 2);
    }

    public function test_defesa_homologada_sem_barema_de_autoavaliacao_nao_finaliza(): void
    {
        [, , $banca, $orientador] = $this->bancaProntaParaFinalizar('defesa');
        Barema::where('tipo', 'autoavaliacao_orientador')->update(['ativo' => false]);

        $this->finalizar($orientador, $banca)
            ->assertSessionHasErrors(['finalizar' => 'Barema de Autoavaliação do orientador não cadastrado.']);

        $this->assertSame('em_avaliacao', $banca->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_orientador_nao_abre_ata_nem_folha_com_autoavaliacao_pendente_na_defesa(): void
    {
        [, , $banca, $orientador] = $this->bancaProntaParaFinalizar();

        $this->actingAs($orientador)->get(route('avaliacoes.ata', $banca))->assertForbidden();
        $this->actingAs($orientador)->get(route('avaliacoes.folha-aprovacao', $banca))->assertForbidden();

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertSee(self::AVISO)
            ->assertDontSee('href="'.route('avaliacoes.ata', $banca).'"', false);

        $this->concluirAutoavaliacao($banca, $orientador);
        $this->actingAs($orientador)->get(route('avaliacoes.ata', $banca))->assertOk();
    }

    public function test_avaliador_abre_ata_e_folha_so_depois_do_envio(): void
    {
        [, , $banca, , $avaliadores] = $this->bancaProntaParaFinalizar('qualificacao');

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.ata', $banca))->assertForbidden();

        $banca->update(['ata_enviada_em' => now()]);
        $this->actingAs($avaliadores[0])->get(route('avaliacoes.ata', $banca))->assertOk();
    }

    public function test_secretaria_abre_ata_mas_nao_ve_botao_de_envio(): void
    {
        [$secretaria, , $banca] = $this->bancaProntaParaFinalizar();

        $this->actingAs($secretaria)->get(route('avaliacoes.ata', $banca))->assertOk();
        $this->actingAs($secretaria)->get(route('bancas.index'))
            ->assertOk()
            ->assertDontSee('Finalizar e enviar ata e folha aos membros');
    }
}
