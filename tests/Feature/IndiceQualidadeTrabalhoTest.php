<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\BancaMembro;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Models\User;
use App\Services\IndiceQualidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class IndiceQualidadeTrabalhoTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private Programa $programa;
    private Banca $banca;
    private Barema $barema;
    private IndiceQualidadeService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->programa, $this->banca] = $this->criarCenarioCompleto('defesa', false);
        $this->banca->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada', 'data_hora' => '2026-05-10 14:00:00']);
        $this->banca->trabalhoAcademico->publicacoes()->delete();

        // Terceiro avaliador, para a mediana.
        $profile = DocenteProfile::create(['user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => 'USP', 'ativo' => true]);
        $vinculo = DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => 'externo', 'pode_orientar' => false, 'pode_avaliar' => true, 'ativo' => true,
        ]);
        BancaMembro::create(['banca_id' => $this->banca->id, 'docente_programa_vinculo_id' => $vinculo->id, 'papel' => 'avaliador', 'ordem' => 3, 'ativo' => true]);

        $this->barema = Barema::create(['programa_id' => $this->programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $this->servico = app(IndiceQualidadeService::class);
    }

    private function item(string $dimensao, float $max = 10, float $peso = 1): BaremaItem
    {
        $secao = $this->barema->secoes()->firstOrCreate(['nome' => 'Seção'], ['peso' => 1, 'ordem' => 1, 'ativo' => true]);

        return BaremaItem::create([
            'barema_secao_id' => $secao->id, 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo($dimensao)->id,
            'nome' => "Item {$dimensao}", 'nota_maxima' => $max, 'peso' => $peso, 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true,
        ]);
    }

    /** $notas: [indice do avaliador => [item_id => nota]] */
    private function notas(array $notas): void
    {
        $membros = $this->banca->avaliadores()->orderBy('ordem')->get()->values();
        foreach ($notas as $i => $porItem) {
            $avaliacao = BancaAvaliacao::create([
                'banca_id' => $this->banca->id, 'banca_membro_id' => $membros[$i]->id, 'barema_id' => $this->barema->id,
                'status' => 'enviada', 'enviada_em' => now(),
            ]);
            foreach ($porItem as $itemId => $nota) {
                $item = BaremaItem::find($itemId);
                $avaliacao->notas()->create(['barema_item_id' => $itemId, 'dimensao_qualidade_id' => $item->dimensao_qualidade_id, 'nota' => $nota]);
            }
        }
    }

    public function test_mediana_dos_avaliadores_por_item(): void
    {
        $rigor = $this->item('rigor');
        $this->notas([[$rigor->id => 6], [$rigor->id => 8], [$rigor->id => 10]]);

        $this->assertEqualsWithDelta(8.0, $this->servico->dimensoesDaBanca($this->banca)['rigor'], 0.001);
    }

    public function test_nota_maxima_diferente_e_normalizada(): void
    {
        $rigor = $this->item('rigor', 5);
        $this->notas([[$rigor->id => 4], [$rigor->id => 4], [$rigor->id => 4]]);

        $this->assertEqualsWithDelta(8.0, $this->servico->dimensoesDaBanca($this->banca)['rigor'], 0.001);
    }

    public function test_dimensao_sem_item_fica_de_fora_e_pesos_renormalizam(): void
    {
        $rigor = $this->item('rigor');
        $original = $this->item('originalidade');
        $this->notas(array_fill(0, 3, [$rigor->id => 8, $original->id => 6]));

        $resultado = $this->servico->indiceTrabalho($this->banca);

        $this->assertNull($resultado['dimensoes']['autonomia']);
        $this->assertEqualsWithDelta(6.93, $resultado['indice'], 0.001);
        $this->assertSame(3, $resultado['classe']);
    }

    public function test_nota_zero_nao_quebra(): void
    {
        $rigor = $this->item('rigor');
        $original = $this->item('originalidade');
        $this->notas(array_fill(0, 3, [$rigor->id => 0, $original->id => 9]));

        $indice = $this->servico->indiceTrabalho($this->banca)['indice'];

        $this->assertTrue(is_finite($indice));
        $this->assertGreaterThan(0, $indice);
    }

    public function test_pontuacao_de_producao(): void
    {
        $trabalho = $this->banca->trabalhoAcademico;
        $this->assertNull($this->servico->pontuacaoProducao($trabalho));

        $trabalho->publicacoes()->create([
            'tipo' => 'artigo', 'titulo' => 'Artigo', 'ano' => 2026, 'veiculo' => 'Revista', 'situacao' => 'publicada',
            'derivada_dissertacao' => true, 'discente_primeiro_autor' => true, 'conferida_em' => now(),
        ]);
        $this->assertEqualsWithDelta(7.5, $this->servico->pontuacaoProducao($trabalho->fresh()), 0.001);

        $rigor = $this->item('rigor');
        $this->notas(array_fill(0, 3, [$rigor->id => 8]));
        $this->assertEqualsWithDelta(7.5, $this->servico->indiceTrabalho($this->banca)['dimensoes']['producao'], 0.001);
    }

    public function test_classes_e_regras_de_corte(): void
    {
        $rigor = $this->item('rigor');
        $original = $this->item('originalidade');
        $relevancia = $this->item('relevancia');

        $this->notas(array_fill(0, 3, [$rigor->id => 10, $original->id => 10, $relevancia->id => 6.9]));
        $resultado = $this->servico->indiceTrabalho($this->banca);
        $this->assertGreaterThanOrEqual(8.5, $resultado['indice']);
        $this->assertSame(2, $resultado['classe']);
        $this->assertContains('Alta qualidade exige todas as dimensões a partir de 7', $resultado['regras']);

        $this->banca->update(['resultado' => 'reprovado']);
        $this->assertSame(4, $this->servico->indiceTrabalho($this->banca->fresh())['classe']);

        // Plágio = jubilamento: o trabalho sai de todas as métricas (sem índice e sem classe).
        $this->banca->update(['reprovada_por_plagio' => true]);
        $plagio = $this->servico->indiceTrabalho($this->banca->fresh());
        $this->assertNull($plagio['classe']);
        $this->assertNull($plagio['indice']);
        $this->assertTrue($plagio['excluido']);
    }

    public function test_dimensao_abaixo_de_4_limita_a_suficiente(): void
    {
        $rigor = $this->item('rigor');
        $original = $this->item('originalidade');
        $relevancia = $this->item('relevancia');
        $autonomia = $this->item('autonomia');
        $this->notas(array_fill(0, 3, [$rigor->id => 10, $original->id => 10, $relevancia->id => 10, $autonomia->id => 3.5]));

        $resultado = $this->servico->indiceTrabalho($this->banca);
        $this->assertGreaterThanOrEqual(7.0, $resultado['indice']);
        $this->assertSame(3, $resultado['classe']);
    }

    public function test_pesos_do_periodo_sao_usados(): void
    {
        $periodo = PeriodoQualidade::create(['programa_id' => $this->programa->id, 'nome' => 'Q', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);
        foreach (PeriodoQualidade::chaves('dimensao') as $i => $chave) {
            $periodo->pesosQualidade()->create(['grupo' => 'dimensao', 'chave' => $chave, 'ordem' => $i + 1, 's_j' => 0,
                'peso' => ['rigor' => 0.75, 'originalidade' => 0.25][$chave] ?? 0]);
        }
        $rigor = $this->item('rigor');
        $original = $this->item('originalidade');
        $this->notas(array_fill(0, 3, [$rigor->id => 8, $original->id => 6]));

        $resultado = $this->servico->indiceTrabalho($this->banca);

        $this->assertEqualsWithDelta(7.44, $resultado['indice'], 0.001);
        $this->assertTrue($resultado['periodo']->is($periodo));
    }

    public function test_defesa_nao_finalizada_sem_indice(): void
    {
        $rigor = $this->item('rigor');
        $this->notas(array_fill(0, 3, [$rigor->id => 8]));
        $this->banca->update(['status' => 'em_avaliacao']);

        $resultado = $this->servico->indiceTrabalho($this->banca->fresh());
        $this->assertNull($resultado['indice']);
        $this->assertNull($resultado['classe']);
    }
}
