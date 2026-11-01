<?php

namespace Tests\Feature;

use App\Mail\CertificadoBancaMail;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\User;
use App\Services\CertificadoBancaLatexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class CertificadoBancaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private const PORTARIA = 'Portaria nº 123/2025, publicada no Diário Oficial do Estado da Bahia em 10/03/2025';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    public function test_tex_tem_nomes_papel_coordenador_e_portaria(): void
    {
        [, $programa, $banca, , , $avaliadores] = $this->cenario();
        $coordenador = User::factory()->docente()->create(['name' => 'Profa. Coordenadora & Cia_Teste']);
        $programa->update(['coordenador_id' => $coordenador->id, 'portaria_coordenador' => self::PORTARIA]);
        $banca->trabalhoAcademico->update(['titulo' => 'Modelos de 100% de cobertura']);

        $tex = app(CertificadoBancaLatexService::class)->renderizarTex($banca->fresh(), $avaliadores[0], 'avaliador');

        $this->assertStringContainsString($avaliadores[0]->name, $tex);
        $this->assertStringContainsString('membro avaliador', $tex);
        $this->assertStringContainsString($banca->trabalhoAcademico->discenteVinculo->discenteProfile->user->name, $tex);
        $this->assertStringContainsString('Modelos de 100\\% de cobertura', $tex);
        $this->assertStringContainsString('Profa. Coordenadora \\& Cia\\_Teste', $tex);
        $this->assertStringContainsString('Coordenador(a) do PPGMSB', $tex);
        $this->assertStringContainsString('Portaria nº 123/2025', $tex);

        $programa->update(['portaria_coordenador' => null]);
        $semPortaria = app(CertificadoBancaLatexService::class)->renderizarTex($banca->fresh(), $avaliadores[0], 'presidente');
        $this->assertStringNotContainsString('Portaria', $semPortaria);
        $this->assertStringContainsString('presidente da banca', $semPortaria);
    }

    public function test_finalizar_envia_certificado_a_avaliadores_e_presidente(): void
    {
        [, , $banca, $orientador, , $avaliadores] = $this->cenario();
        $this->falsificarPdf();

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasNoErrors();

        Mail::assertSent(CertificadoBancaMail::class, 3);
        foreach ($avaliadores as $avaliador) {
            Mail::assertSent(CertificadoBancaMail::class, fn (CertificadoBancaMail $m) => $m->hasTo($avaliador->email) && $m->papel === 'avaliador');
        }
        Mail::assertSent(CertificadoBancaMail::class, fn (CertificadoBancaMail $m) => $m->hasTo($orientador->email) && $m->papel === 'presidente');
    }

    public function test_falha_no_certificado_nao_desfaz_finalizacao(): void
    {
        [, , $banca, $orientador] = $this->cenario();
        $servico = Mockery::mock(CertificadoBancaLatexService::class);
        $servico->shouldReceive('gerarPdf')->andThrow(new \RuntimeException('pdflatex falhou'));
        $this->app->instance(CertificadoBancaLatexService::class, $servico);

        $this->actingAs($orientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'certificado'));

        $this->assertSame('finalizada', $banca->fresh()->status);
        Mail::assertNotSent(CertificadoBancaMail::class);
    }

    public function test_participante_baixa_o_certificado_depois(): void
    {
        [, , $banca, $orientador, $discente, $avaliadores] = $this->cenario();
        $this->falsificarPdf();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.certificado', $banca))->assertForbidden();

        $banca->update(['status' => 'finalizada', 'resultado' => 'aprovado']);
        $this->actingAs($avaliadores[0])->get(route('avaliacoes.certificado', $banca))->assertOk();
        $this->actingAs($orientador)->get(route('avaliacoes.certificado', $banca))->assertOk();
        $this->actingAs(User::factory()->docente()->create())->get(route('avaliacoes.certificado', $banca))->assertForbidden();
        $this->actingAs($discente)->get(route('avaliacoes.certificado', $banca))->assertForbidden();

        $this->actingAs($orientador)->get(route('avaliacoes.banca', $banca))
            ->assertSee(route('avaliacoes.certificado', $banca), false);
    }

    public function test_titulo_com_letras_gregas_e_simbolos_compila(): void
    {
        if (trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where pdflatex 2>NUL' : 'command -v pdflatex 2>/dev/null')) === '') {
            $this->markTestSkipped('pdflatex não instalado.');
        }
        [, , $banca, , , $avaliadores] = $this->cenario();
        $banca->trabalhoAcademico->update(['titulo' => 'Estimativa de R₀ e β ≤ 0,5 em modelos SIR — análise “robusta” 東']);

        $tex = app(CertificadoBancaLatexService::class)->renderizarTex($banca->fresh(), $avaliadores[0], 'avaliador');
        $this->assertStringContainsString('\ensuremath{\beta}', $tex);

        $caminho = app(CertificadoBancaLatexService::class)->gerarPdf($banca->fresh(), $avaliadores[0], 'avaliador');
        Storage::disk('local')->assertExists($caminho);
    }

    public function test_falha_ao_baixar_volta_com_mensagem_em_vez_de_erro_500(): void
    {
        [, , $banca, $orientador] = $this->cenario();
        $banca->update(['status' => 'finalizada', 'resultado' => 'aprovado']);
        $servico = Mockery::mock(CertificadoBancaLatexService::class);
        $servico->shouldReceive('gerarPdf')->andThrow(new \RuntimeException('pdflatex falhou'));
        $this->app->instance(CertificadoBancaLatexService::class, $servico);

        $this->actingAs($orientador)->from(route('avaliacoes.banca', $banca))->get(route('avaliacoes.certificado', $banca))
            ->assertRedirect(route('avaliacoes.banca', $banca))
            ->assertSessionHasErrors('certificado');
    }

    public function test_certificado_de_presidente_vai_ao_orientador_mesmo_se_o_coorientador_finalizar(): void
    {
        [$secretaria, $programa, $banca, $discente, $orientador, $coorientador] = $this->criarCenarioCompleto('qualificacao');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);
        foreach ($banca->avaliadores()->get() as $membro) {
            foreach (Barema::whereIn('tipo', ['qualificacao', 'qualificacao_confidencial'])->get() as $barema) {
                BancaAvaliacao::create([
                    'banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id,
                    'status' => 'enviada', 'nota_final' => 8, 'enviada_em' => now(),
                ]);
            }
        }
        $this->falsificarPdf();

        $this->actingAs($coorientador)->post(route('avaliacoes.finalizar', $banca), ['resultado' => 'aprovado']);

        Mail::assertSent(CertificadoBancaMail::class, fn (CertificadoBancaMail $m) => $m->hasTo($orientador->email) && $m->papel === 'presidente');
        Mail::assertNotSent(CertificadoBancaMail::class, fn (CertificadoBancaMail $m) => $m->hasTo($coorientador->email));
        $this->actingAs($coorientador)->get(route('avaliacoes.banca', $banca))
            ->assertDontSee(route('avaliacoes.certificado', $banca), false);
    }

    public function test_secretaria_cadastra_portaria_do_coordenador(): void
    {
        [$secretaria, $programa] = $this->cenario();

        $this->actingAs($secretaria)->put(route('programas.update', $programa), [
            'nome' => $programa->nome, 'instituicao_id' => $programa->instituicao_id, 'portaria_coordenador' => self::PORTARIA,
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::PORTARIA, $programa->fresh()->portaria_coordenador);
        $this->actingAs($secretaria)->get(route('programas.edit', $programa))->assertSee('Portaria de designação do(a) coordenador(a)');
    }

    private function falsificarPdf(): void
    {
        $servico = Mockery::mock(CertificadoBancaLatexService::class);
        $servico->shouldReceive('gerarPdf')->andReturnUsing(function ($banca, $user) {
            $caminho = "bancas/{$banca->id}/certificado-{$user->id}.pdf";
            Storage::disk('local')->put($caminho, '%PDF-1.4 teste');

            return $caminho;
        });
        $this->app->instance(CertificadoBancaLatexService::class, $servico);
    }

    /** Qualificação homologada com todas as avaliações enviadas (pronta para finalizar). */
    private function cenario(): array
    {
        [$secretaria, $programa, $banca, $discente, $orientador, , , $avaliadores] = $this->criarCenarioCompleto('qualificacao');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);
        foreach ($banca->avaliadores()->get() as $membro) {
            foreach (Barema::whereIn('tipo', ['qualificacao', 'qualificacao_confidencial'])->get() as $barema) {
                BancaAvaliacao::create([
                    'banca_id' => $banca->id, 'banca_membro_id' => $membro->id, 'barema_id' => $barema->id,
                    'status' => 'enviada', 'nota_final' => 8, 'enviada_em' => now(),
                ]);
            }
        }

        return [$secretaria, $programa, $banca->fresh(), $orientador, $discente, $avaliadores];
    }
}
