<?php

namespace Tests\Feature;

use App\Models\AtividadeFormativa;
use App\Models\AtividadeRealizada;
use App\Models\Banca;
use App\Models\ProducaoPublicacao;
use App\Models\User;
use App\Services\HomologacaoBancaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class ConferenciaSecretariaTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    public function test_secretaria_confere_lancamentos_e_registra_as_que_ela_informa(): void
    {
        [$secretaria, $banca, $extensao, $proficiencia, $lancamento] = $this->cenario();

        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))
            ->assertOk()
            ->assertSee('Atividades formativas')
            ->assertSee('Atividade Extensionista')
            ->assertSee('Oficina na escola')
            ->assertSee('Proficiência em Língua Estrangeira');

        $this->actingAs($secretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$lancamento->id => 'confirmada'],
            'registrar' => [$proficiencia->id => '1'],
        ])->assertSessionHasNoErrors();

        $lancamento->refresh();
        $this->assertSame('confirmada', $lancamento->conferencia);
        $this->assertSame($secretaria->id, $lancamento->conferida_por);
        $this->assertNotNull($lancamento->conferida_em);

        $registrada = AtividadeRealizada::where('atividade_formativa_id', $proficiencia->id)->sole();
        $this->assertSame('Registrada pela Secretaria', $registrada->descricao);
        $this->assertSame('confirmada', $registrada->conferencia);
    }

    public function test_atividades_pendentes_bloqueiam_homologacao(): void
    {
        [$secretaria, $banca, , $proficiencia, $lancamento] = $this->cenario();
        $servico = app(HomologacaoBancaService::class);

        $analise = $servico->podeHomologar($banca->fresh());
        $this->assertFalse($analise['pode']);
        $this->assertContains('Há 1 lançamento(s) de atividade formativa sem conferência.', $analise['pendencias']);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$lancamento->id => 'confirmada'],
        ]);
        $analise = $servico->podeHomologar($banca->fresh());
        $this->assertContains('Atividades formativas incompletas: Proficiência em Língua Estrangeira (0 de 1).', $analise['pendencias']);
        $this->assertFalse($analise['atividades_ok']);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'registrar' => [$proficiencia->id => '1'],
        ]);
        $this->assertTrue($servico->podeHomologar($banca->fresh())['atividades_ok']);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$lancamento->id => 'nao_confirmada'],
        ]);
        $analise = $servico->podeHomologar($banca->fresh());
        $this->assertContains('Atividades formativas incompletas: Atividade Extensionista (0 de 1).', $analise['pendencias']);
    }

    public function test_secretaria_marca_publicacoes_na_defesa(): void
    {
        [$secretaria, $banca] = $this->cenario();
        $publicacao = $this->publicacao($banca);

        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))
            ->assertSee('Artigo derivado');

        $this->actingAs($secretaria)->post(route('bancas.homologacao.publicacoes', $banca), [
            'publicacoes' => [$publicacao->id => [
                'situacao' => 'publicada', 'derivada_dissertacao' => '1', 'discente_primeiro_autor' => '1',
                'coautoria_orientador' => '1', 'coautoria_internacional' => '0', 'conta_minimo' => '1',
            ]],
        ])->assertSessionHasNoErrors();

        $publicacao->refresh();
        $this->assertSame('publicada', $publicacao->situacao);
        $this->assertTrue($publicacao->derivada_dissertacao);
        $this->assertTrue($publicacao->discente_primeiro_autor);
        $this->assertTrue($publicacao->coautoria_orientador);
        $this->assertFalse($publicacao->coautoria_internacional);
        $this->assertTrue($publicacao->conta_minimo);
        $this->assertSame($secretaria->id, $publicacao->conferida_por);
        $this->assertNotNull($publicacao->conferida_em);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.publicacoes', $banca), [
            'publicacoes' => [$publicacao->id => ['situacao' => 'no_prelo']],
        ])->assertSessionHasErrors();
    }

    public function test_publicacoes_pendentes_ou_abaixo_do_minimo_bloqueiam_homologacao_da_defesa(): void
    {
        [$secretaria, $banca] = $this->cenario();
        $publicacao = $this->publicacao($banca);
        $servico = app(HomologacaoBancaService::class);

        $analise = $servico->podeHomologar($banca->fresh());
        $this->assertContains('Há 1 publicação(ões) sem conferência da Secretaria.', $analise['pendencias']);
        $this->assertFalse($analise['publicacoes_ok']);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.publicacoes', $banca), [
            'publicacoes' => [$publicacao->id => ['situacao' => 'submetida', 'conta_minimo' => '0']],
        ]);
        $analise = $servico->podeHomologar($banca->fresh());
        $this->assertContains('Publicações que contam para o mínimo do curso: 0 de 1.', $analise['pendencias']);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.publicacoes', $banca), [
            'publicacoes' => [$publicacao->id => ['situacao' => 'aceita', 'conta_minimo' => '1']],
        ]);
        $this->assertTrue($servico->podeHomologar($banca->fresh())['publicacoes_ok']);
    }

    public function test_qualificacao_mostra_atividades_da_etapa_e_nao_publicacoes(): void
    {
        [$secretaria, , $programa, $banca] = $this->cenarioBase('qualificacao');
        AtividadeFormativa::create([
            'programa_id' => $programa->id, 'nome' => 'Seminário de pesquisa', 'quantidade_exigida' => 1,
            'etapa' => 'qualificacao', 'informada_por' => 'estudante', 'ordem' => 9,
        ]);

        $this->actingAs($secretaria)->get(route('bancas.homologacao.show', $banca))
            ->assertOk()
            ->assertSee('Seminário de pesquisa')
            ->assertDontSee('Atividade Extensionista')
            ->assertDontSee('Publicações da produção associada');

        $this->assertTrue(app(HomologacaoBancaService::class)->podeHomologar($banca)['publicacoes_ok']);
    }

    public function test_so_secretaria_do_programa_confere(): void
    {
        [$secretaria, $banca, , , $lancamento] = $this->cenario();
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$lancamento->id => 'confirmada'],
        ])->assertForbidden();

        [$outraSecretaria] = $this->cenarioBase('defesa');
        $this->actingAs($outraSecretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$lancamento->id => 'confirmada'],
        ])->assertNotFound();

        $this->actingAs($secretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$lancamento->id => 'talvez'],
        ])->assertSessionHasErrors();

        // Lançamento de outro trabalho é ignorado.
        [, $outraBanca] = $this->cenario();
        $alheio = AtividadeRealizada::where('trabalho_academico_id', $outraBanca->trabalho_academico_id)->whereNull('conferencia')->firstOrFail();
        $this->actingAs($secretaria)->post(route('bancas.homologacao.atividades', $banca), [
            'conferencias' => [$alheio->id => 'confirmada'],
        ])->assertSessionHasNoErrors();
        $this->assertNull($alheio->fresh()->conferencia);
        $this->assertNull($lancamento->fresh()->conferencia);
    }

    public function test_estudante_nao_exclui_publicacao_conferida(): void
    {
        [, $banca, , , , $discente] = $this->cenario();
        $publicacao = $this->publicacao($banca);
        $publicacao->update(['situacao' => 'publicada', 'conta_minimo' => true, 'conferida_em' => now()]);
        $banca->update(['homologacao_status' => 'devolvida']);

        $this->actingAs($discente)->delete(route('meu-trabalho.producao.destroy', ['publicacao', $publicacao->id]))
            ->assertSessionHasErrors('publicacao');
        $this->assertModelExists($publicacao);
    }

    private function publicacao(Banca $banca): ProducaoPublicacao
    {
        return ProducaoPublicacao::create([
            'trabalho_academico_id' => $banca->trabalho_academico_id, 'tipo' => 'artigo',
            'titulo' => 'Artigo derivado', 'ano' => 2025, 'veiculo' => 'Revista X',
        ]);
    }

    /** Defesa aguardando com as 4 atividades padrão: um lançamento da Extensão; Publicação e Popularização já confirmadas. */
    private function cenario(): array
    {
        [$secretaria, $discente, $programa, $banca] = $this->cenarioBase('defesa');
        AtividadeFormativa::criarPadroes($programa);
        $extensao = AtividadeFormativa::where('programa_id', $programa->id)->where('nome', 'Atividade Extensionista')->sole();
        $proficiencia = AtividadeFormativa::where('programa_id', $programa->id)->where('nome', 'Proficiência em Língua Estrangeira')->sole();
        foreach (['Publicação de Produção Científica', 'Popularização das Ciências'] as $nome) {
            AtividadeRealizada::create([
                'trabalho_academico_id' => $banca->trabalho_academico_id,
                'atividade_formativa_id' => AtividadeFormativa::where('programa_id', $programa->id)->where('nome', $nome)->value('id'),
                'descricao' => $nome, 'conferencia' => 'confirmada', 'conferida_em' => now(),
            ]);
        }
        $lancamento = AtividadeRealizada::create([
            'trabalho_academico_id' => $banca->trabalho_academico_id,
            'atividade_formativa_id' => $extensao->id,
            'descricao' => 'Oficina na escola',
        ]);

        return [$secretaria, $banca, $extensao, $proficiencia, $lancamento, $discente];
    }

    /** @return array{0: User, 1: User, 2: \App\Models\Programa, 3: Banca} */
    private function cenarioBase(string $tipo): array
    {
        [$secretaria, $programa, $banca, $discente] = $this->criarCenarioCompleto($tipo);
        // A publicação e os lançamentos padrão do cenário de defesa saem para o teste controlar as listas.
        ProducaoPublicacao::where('trabalho_academico_id', $banca->trabalho_academico_id)->delete();
        AtividadeRealizada::where('trabalho_academico_id', $banca->trabalho_academico_id)->delete();

        return [$secretaria, $discente, $programa, $banca];
    }
}
