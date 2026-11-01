<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaRecomendacao;
use App\Models\Barema;
use App\Models\RecomendacaoResposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class RecomendacoesQualificacaoTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_avaliador_registra_recomendacoes_no_formulario_padrao_da_qualificacao(): void
    {
        [, $banca, , $avaliadores] = $this->qualificacao();
        $padrao = Barema::where('tipo', 'qualificacao')->firstOrFail();
        $confidencial = Barema::where('tipo', 'qualificacao_confidencial')->firstOrFail();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.form', [$banca, $padrao]))
            ->assertOk()->assertSee('Recomendações ao estudante (uma por linha)');
        $this->actingAs($avaliadores[0])->get(route('avaliacoes.form', [$banca, $confidencial]))
            ->assertOk()->assertDontSee('Recomendações ao estudante');

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $padrao]), [
            'acao' => 'rascunho', 'recomendacoes' => "Ampliar a revisão de literatura.\n\n  Justificar o tamanho da amostra.  ",
        ])->assertSessionHasNoErrors();
        $this->assertSame(
            ['Ampliar a revisão de literatura.', 'Justificar o tamanho da amostra.'],
            BancaRecomendacao::where('banca_id', $banca->id)->orderBy('ordem')->pluck('texto')->all(),
        );

        $this->actingAs($avaliadores[0])->post(route('avaliacoes.salvar', [$banca, $padrao]), [
            'acao' => 'rascunho', 'recomendacoes' => 'Só esta.',
        ]);
        $this->assertSame(['Só esta.'], BancaRecomendacao::where('banca_id', $banca->id)->pluck('texto')->all());
    }

    public function test_defesa_nao_tem_campo_de_recomendacoes(): void
    {
        [, , , , $avaliadores, $defesa] = $this->defesaComRecomendacoes();
        $defesa->update(['homologacao_status' => 'homologada']);
        $padrao = Barema::where('tipo', 'defesa')->firstOrFail();

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.form', [$defesa, $padrao]))
            ->assertOk()->assertDontSee('Recomendações ao estudante (uma por linha)');
    }

    public function test_estudante_responde_na_defesa(): void
    {
        [$discente, , [$r1, $r2]] = $this->defesaComRecomendacoes();

        $this->actingAs($discente)->get(route('meu-trabalho.recomendacoes.edit'))
            ->assertOk()
            ->assertSee('Ampliar a revisão de literatura.')
            ->assertSee('Justificar o tamanho da amostra.')
            ->assertDontSee('Prof. Avaliador');

        $this->actingAs($discente)->put(route('meu-trabalho.recomendacoes.update'), ['respostas' => [
            $r1->id => ['situacao' => 'atendida', 'onde' => 'Cap. 3, p. 40'],
            $r2->id => ['situacao' => 'nao_atendida', 'justificativa' => 'A amostra é censitária.'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('atendida', RecomendacaoResposta::where('banca_recomendacao_id', $r1->id)->value('situacao'));
        $this->assertSame('A amostra é censitária.', RecomendacaoResposta::where('banca_recomendacao_id', $r2->id)->value('justificativa'));
    }

    public function test_regras_das_respostas(): void
    {
        [$discente, , [$r1, $r2]] = $this->defesaComRecomendacoes();

        $this->actingAs($discente)->put(route('meu-trabalho.recomendacoes.update'), ['respostas' => [
            $r1->id => ['situacao' => 'atendida', 'onde' => ''],
            $r2->id => ['situacao' => 'parcial', 'onde' => 'Cap. 2', 'justificativa' => ''],
        ]])->assertSessionHasErrors(["respostas.{$r1->id}.onde", "respostas.{$r2->id}.justificativa"]);

        $this->actingAs($discente)->put(route('meu-trabalho.recomendacoes.update'), ['respostas' => [
            $r1->id => ['situacao' => 'nao_atendida', 'justificativa' => ''],
        ]])->assertSessionHasErrors(["respostas.{$r1->id}.justificativa", "respostas.{$r2->id}.situacao"]);

        $this->assertDatabaseCount('recomendacao_respostas', 0);
    }

    public function test_banca_da_defesa_ve_recomendacoes_e_respostas(): void
    {
        [, , [$r1], , $avaliadores, $defesa] = $this->defesaComRecomendacoes();
        RecomendacaoResposta::create(['banca_recomendacao_id' => $r1->id, 'situacao' => 'atendida', 'onde' => 'Cap. 3, p. 40']);
        $defesa->update(['homologacao_status' => 'homologada']);

        $this->actingAs($avaliadores[0])->get(route('avaliacoes.form', [$defesa, Barema::where('tipo', 'defesa')->firstOrFail()]))
            ->assertSee('Recomendações da qualificação')
            ->assertSee('Ampliar a revisão de literatura.')
            ->assertSee('Cap. 3, p. 40');
    }

    public function test_trava_com_pedido_de_defesa_aguardando(): void
    {
        [$discente, , [$r1, $r2], , , $defesa] = $this->defesaComRecomendacoes();
        $defesa->update(['homologacao_status' => 'aguardando']);

        $this->actingAs($discente)->put(route('meu-trabalho.recomendacoes.update'), ['respostas' => [
            $r1->id => ['situacao' => 'atendida', 'onde' => 'x'],
            $r2->id => ['situacao' => 'atendida', 'onde' => 'y'],
        ]])->assertSessionHasErrors('travado');
        $this->assertDatabaseCount('recomendacao_respostas', 0);
    }

    public function test_outro_perfil_nao_acessa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('meu-trabalho.recomendacoes.edit'))->assertForbidden();
        $this->actingAs($docente)->put(route('meu-trabalho.recomendacoes.update'), ['respostas' => []])->assertForbidden();
    }

    /** @return array{0: User, 1: Banca, 2: User, 3: list<User>, 4: User} */
    private function qualificacao(): array
    {
        [$secretaria, , $banca, $discente, $orientador, , , $avaliadores] = $this->criarCenarioCompleto('qualificacao');
        $banca->update(['homologacao_status' => 'homologada', 'status' => 'em_avaliacao']);

        return [$discente, $banca->fresh(), $orientador, $avaliadores, $secretaria];
    }

    /** Qualificação finalizada com 2 recomendações e uma banca de defesa proposta. */
    private function defesaComRecomendacoes(): array
    {
        [$discente, $qualificacao, $orientador, $avaliadores] = $this->qualificacao();
        $qualificacao->update(['status' => 'finalizada', 'resultado' => 'aprovado']);
        $membro = $qualificacao->avaliadores()->firstOrFail();
        $r1 = BancaRecomendacao::create(['banca_id' => $qualificacao->id, 'banca_membro_id' => $membro->id, 'texto' => 'Ampliar a revisão de literatura.', 'ordem' => 1]);
        $r2 = BancaRecomendacao::create(['banca_id' => $qualificacao->id, 'banca_membro_id' => $membro->id, 'texto' => 'Justificar o tamanho da amostra.', 'ordem' => 2]);

        $defesa = Banca::create([
            'trabalho_academico_id' => $qualificacao->trabalho_academico_id, 'tipo' => 'defesa', 'status' => 'cadastrada',
            'homologacao_status' => 'proposta', 'homologacao_tentativa' => 1, 'libera_envio_trabalho' => false,
        ]);
        foreach ($qualificacao->avaliadores()->get() as $m) {
            $defesa->membros()->create(['docente_programa_vinculo_id' => $m->docente_programa_vinculo_id, 'papel' => 'avaliador', 'ordem' => $m->ordem, 'ativo' => true]);
        }

        return [$discente, $qualificacao, [$r1, $r2], $orientador, $avaliadores, $defesa];
    }
}
