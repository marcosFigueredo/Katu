<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class PrivacidadeAvaliacoesTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    /** Qualificação em avaliação: padrão com nota 8,0 e confidencial com nota 3,3 enviadas pelos 2 avaliadores. */
    private function cenario(): array
    {
        Mail::fake();
        [$secretaria, , $banca, , $orientador, , , $avaliadores] = $this->criarCenarioCompleto('qualificacao');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);

        $padrao = Barema::where('tipo', 'qualificacao')->firstOrFail();
        $confidencial = Barema::where('tipo', 'qualificacao_confidencial')->firstOrFail();
        foreach ($banca->avaliadores()->get() as $membro) {
            foreach ([[$padrao, 8], [$confidencial, 3.3]] as [$barema, $nota]) {
                BancaAvaliacao::create([
                    'banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id,
                    'status' => 'enviada', 'nota_final' => $nota, 'enviada_em' => now(),
                ]);
            }
        }

        return [$secretaria, $banca->fresh(), $orientador, $avaliadores, $padrao, $confidencial];
    }

    public function test_orientador_nao_ve_nota_nem_respostas_confidenciais_na_banca(): void
    {
        [, $banca, $orientador, , , $confidencial] = $this->cenario();

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertSee('Confidencial')
            ->assertSee('Enviada')
            ->assertSee('8.0')
            ->assertDontSee('3.3')
            ->assertDontSee(route('avaliacoes.relatorio-individual', [$banca, $confidencial]), false);
    }

    public function test_consolidado_do_orientador_nao_tem_barema_confidencial(): void
    {
        [, $banca, $orientador, , $padrao, $confidencial] = $this->cenario();

        $this->actingAs($orientador)->get(route('avaliacoes.relatorio-consolidado', $banca))
            ->assertOk()
            ->assertSee($padrao->nome)
            ->assertDontSee($confidencial->nome)
            ->assertDontSee('3,30')
            ->assertDontSee('3.30');
    }

    public function test_orientador_recebe_403_no_relatorio_individual_confidencial(): void
    {
        [, $banca, $orientador, , , $confidencial] = $this->cenario();

        $this->actingAs($orientador)->get(route('avaliacoes.relatorio-individual', [$banca, $confidencial]))
            ->assertForbidden();
    }

    public function test_avaliador_ve_o_proprio_relatorio_confidencial(): void
    {
        [, $banca, , $avaliadores, , $confidencial] = $this->cenario();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.relatorio-individual', [$banca, $confidencial]))
            ->assertOk();
    }

    public function test_secretaria_recebe_403_nos_relatorios(): void
    {
        [$secretaria, $banca, , , $padrao, $confidencial] = $this->cenario();

        $this->actingAs($secretaria)->get(route('avaliacoes.relatorio-individual', [$banca, $padrao]))->assertForbidden();
        $this->actingAs($secretaria)->get(route('avaliacoes.relatorio-individual', [$banca, $confidencial]))->assertForbidden();
        $this->actingAs($secretaria)->get(route('avaliacoes.relatorio-consolidado', $banca))->assertForbidden();
    }

    public function test_nota_media_da_banca_nao_muda(): void
    {
        [, $banca, $orientador] = $this->cenario();

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasNoErrors();

        // Média de todas as avaliações enviadas, confidenciais incluídas: (8 + 3,3) / 2 = 5,65.
        $this->assertEquals(5.65, Banca::find($banca->id)->nota_media);
    }
}
