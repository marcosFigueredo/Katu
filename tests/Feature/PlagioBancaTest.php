<?php

namespace Tests\Feature;

use App\Models\AutoavaliacaoOrientador;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\User;
use App\Services\IntegridadeBancaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class PlagioBancaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private const PENDENCIA = 'Há uma pendência de integridade a ser resolvida pela banca. Os avaliadores precisam se alinhar antes da finalização: quem marcou plágio desmarca, ou os demais também marcam, no quadro "Plágio" da tela da banca.';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_check_de_plagio_so_no_formulario_confidencial(): void
    {
        [, $banca, , $avaliadores] = $this->cenario(enviar: false);
        $confidencial = Barema::where('tipo', 'defesa_confidencial')->firstOrFail();
        $padrao = Barema::where('tipo', 'defesa')->firstOrFail();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.form', [$banca, $confidencial]))
            ->assertOk()->assertSee('Identifiquei plágio');
        $this->actingAs($avaliadores[0])->get(route('avaliacoes.form', [$banca, $padrao]))
            ->assertOk()->assertDontSee('Identifiquei plágio');

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $padrao]), [
            'acao' => 'rascunho', 'plagio_identificado' => '1', 'plagio_indicacao' => 'p. 10',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(BancaAvaliacao::where('barema_id', $padrao->id)->sole()->plagio_identificado);
    }

    public function test_plagio_marcado_exige_indicacao(): void
    {
        [, $banca, , $avaliadores] = $this->cenario(enviar: false);
        $confidencial = Barema::where('tipo', 'defesa_confidencial')->firstOrFail();

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $confidencial]), [
            'acao' => 'rascunho', 'plagio_identificado' => '1', 'plagio_indicacao' => '',
        ])->assertSessionHasErrors('plagio_indicacao');

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $confidencial]), [
            'acao' => 'rascunho', 'plagio_identificado' => '1', 'plagio_indicacao' => 'Cap. 2, p. 15-18, copiado de Silva (2020).',
        ])->assertSessionHasNoErrors();
        $avaliacao = BancaAvaliacao::where('barema_id', $confidencial->id)->sole();
        $this->assertTrue($avaliacao->plagio_identificado);

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $confidencial]), [
            'acao' => 'rascunho', 'plagio_identificado' => '0', 'plagio_indicacao' => 'texto antigo',
        ])->assertSessionHasNoErrors();
        $avaliacao->refresh();
        $this->assertFalse($avaliacao->plagio_identificado);
        $this->assertNull($avaliacao->plagio_indicacao);
    }

    public function test_marcacao_parcial_bloqueia_finalizacao_e_documentos(): void
    {
        [, $banca, $orientador, $avaliadores] = $this->cenario();
        $this->marcar($banca, $avaliadores[0], 'Cap. 2, p. 15-18, copiado de Silva (2020).');
        $this->concluirAutoavaliacao($banca, $orientador);

        $this->assertSame('parcial', app(IntegridadeBancaService::class)->situacao($banca));

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasErrors(['finalizar' => self::PENDENCIA]);
        $this->assertSame('em_avaliacao', $banca->fresh()->status);

        $this->actingAs($orientador)->get(route('avaliacoes.ata', $banca))->assertForbidden();
        $this->actingAs($orientador)->get(route('avaliacoes.folha-aprovacao', $banca))->assertForbidden();

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertSee('Há uma pendência de integridade a ser resolvida pela banca')
            ->assertDontSee('copiado de Silva');
        $this->actingAs($avaliadores[1])->get(route('avaliacoes.banca', $banca))
            ->assertSee('copiado de Silva');
    }

    public function test_avaliador_sem_confidencial_enviada_nao_conta_como_unanime(): void
    {
        [, $banca, , $avaliadores] = $this->cenario();
        $this->marcar($banca, $avaliadores[0], 'p. 10');
        $outra = $this->confidencialDe($banca, $avaliadores[1]);
        $outra->update(['plagio_identificado' => true, 'plagio_indicacao' => 'p. 10', 'status' => 'rascunho']);

        $this->assertSame('parcial', app(IntegridadeBancaService::class)->situacao($banca));
    }

    public function test_unanimidade_reprova_mesmo_com_aprovado_escolhido(): void
    {
        [, $banca, $orientador, $avaliadores] = $this->cenario();
        foreach ($avaliadores as $avaliador) {
            $this->marcar($banca, $avaliador, 'Cap. 2 copiado de Silva (2020).');
        }
        $this->concluirAutoavaliacao($banca, $orientador);
        $this->assertSame('unanime', app(IntegridadeBancaService::class)->situacao($banca));

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasNoErrors();

        $banca->refresh();
        $this->assertSame('finalizada', $banca->status);
        $this->assertSame('reprovado', $banca->resultado);
        $this->assertTrue($banca->reprovada_por_plagio);
        $this->assertNotSame('defendido', $banca->trabalhoAcademico->fresh()->status);

        $this->actingAs($orientador)->get(route('avaliacoes.ata', $banca))
            ->assertOk()
            ->assertSee('Reprovado por plágio identificado pela banca (decisão unânime)');
        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertSee('Reprovada por plágio identificado pela banca (decisão unânime)');
    }

    public function test_orientador_e_avisado_antes_de_finalizar_com_plagio_unanime(): void
    {
        [, $banca, $orientador, $avaliadores] = $this->cenario();
        foreach ($avaliadores as $avaliador) {
            $this->marcar($banca, $avaliador, 'Cap. 2 copiado.');
        }
        $this->concluirAutoavaliacao($banca, $orientador);

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertSee('A banca identificou plágio por unanimidade; o resultado será Reprovado.')
            ->assertSee('<input type="hidden" name="resultado" value="reprovado">', false);

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'plágio'));
    }

    public function test_avaliacao_enviada_nao_pode_ser_alterada_pelo_formulario(): void
    {
        [, $banca, , $avaliadores] = $this->cenario();
        $confidencial = Barema::where('tipo', 'defesa_confidencial')->firstOrFail();
        $avaliacao = $this->confidencialDe($banca, $avaliadores[0]);

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $confidencial]), [
            'acao' => 'rascunho', 'observacoes_gerais' => 'Mudança tardia', 'plagio_identificado' => '1', 'plagio_indicacao' => 'p. 3',
        ])->assertSessionHasErrors('avaliacao');

        $avaliacao->refresh();
        $this->assertNull($avaliacao->observacoes_gerais);
        $this->assertFalse($avaliacao->plagio_identificado);
    }

    public function test_avaliadores_se_alinham_no_quadro_de_plagio(): void
    {
        [, $banca, $orientador, $avaliadores] = $this->cenario();
        $this->marcar($banca, $avaliadores[0], 'Cap. 2 copiado.');
        $servico = app(IntegridadeBancaService::class);

        $this->actingAs($avaliadores[1])->get(route('avaliacoes.banca', $banca))
            ->assertSee('action="'.route('avaliacoes.plagio', $banca).'"', false);

        // Quem marcou desmarca: a pendência some.
        $this->actingAs($avaliadores[0])->post(route('avaliacoes.plagio', $banca), ['plagio_identificado' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertSame('nenhuma', $servico->situacao($banca));

        // Os dois marcam: unanimidade.
        foreach ($avaliadores as $avaliador) {
            $this->actingAs($avaliador)->post(route('avaliacoes.plagio', $banca), [
                'plagio_identificado' => '1', 'plagio_indicacao' => 'Cap. 2 copiado.',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame('unanime', $servico->situacao($banca));

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.plagio', $banca), ['plagio_identificado' => '1', 'plagio_indicacao' => ''])
            ->assertSessionHasErrors('plagio_indicacao');
        $this->actingAs($orientador)->post(route('avaliacoes.plagio', $banca), ['plagio_identificado' => '0'])->assertForbidden();

        $banca->update(['status' => 'finalizada']);
        $this->actingAs($avaliadores[0])->post(route('avaliacoes.plagio', $banca), ['plagio_identificado' => '0'])->assertForbidden();
    }

    public function test_secretaria_nao_ve_indicacoes(): void
    {
        [$secretaria, $banca, , $avaliadores] = $this->cenario();
        $this->marcar($banca, $avaliadores[0], 'copiado de Silva');

        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))->assertDontSee('copiado de Silva');
        $this->actingAs($secretaria)->get(route('bancas.index'))->assertDontSee('copiado de Silva');
    }

    private function marcar(Banca $banca, User $avaliador, string $indicacao): void
    {
        $this->confidencialDe($banca, $avaliador)->update(['plagio_identificado' => true, 'plagio_indicacao' => $indicacao]);
    }

    private function confidencialDe(Banca $banca, User $avaliador): BancaAvaliacao
    {
        $membro = $banca->avaliadores()->get()->first(fn ($m) => $m->docenteProgramaVinculo->docenteProfile->user_id === $avaliador->id);
        $barema = Barema::where('tipo', "{$banca->tipo}_confidencial")->firstOrFail();

        return BancaAvaliacao::firstOrCreate(
            ['banca_membro_id' => $membro->id, 'barema_id' => $barema->id],
            ['banca_id' => $banca->id, 'status' => 'enviada', 'nota_final' => 8, 'enviada_em' => now()],
        );
    }

    private function concluirAutoavaliacao(Banca $banca, User $orientador): void
    {
        AutoavaliacaoOrientador::create([
            'banca_id' => $banca->id,
            'docente_programa_vinculo_id' => $orientador->docenteProfile->vinculosPrograma()->first()->id,
            'barema_id' => Barema::where('tipo', 'autoavaliacao_orientador')->value('id'),
            'nota_final' => 9, 'enviada_em' => now(),
        ]);
        // Na defesa o presidente também avalia o orientando antes de finalizar.
        \App\Models\AvaliacaoOrientando::create([
            'banca_id' => $banca->id,
            'docente_programa_vinculo_id' => $orientador->docenteProfile->vinculosPrograma()->first()->id,
            'enviada_em' => now(),
        ]);
    }

    /** Defesa homologada em avaliação; com $enviar, todas as avaliações (padrão e confidencial) já enviadas. */
    private function cenario(bool $enviar = true): array
    {
        [$secretaria, , $banca, , $orientador, , , $avaliadores] = $this->criarCenarioCompleto('defesa');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);

        if ($enviar) {
            foreach ($banca->avaliadores()->get() as $membro) {
                foreach (Barema::whereIn('tipo', ['defesa', 'defesa_confidencial'])->get() as $barema) {
                    BancaAvaliacao::create([
                        'banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id,
                        'status' => 'enviada', 'nota_final' => 8, 'enviada_em' => now(),
                    ]);
                }
            }
        }

        return [$secretaria, $banca->fresh(), $orientador, $avaliadores];
    }
}
