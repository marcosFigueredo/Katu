<?php

namespace Tests\Feature;

use App\Models\AcessoCpa;
use App\Models\AutoavaliacaoOrientador;
use App\Models\Banca;
use App\Models\BancaAvaliacao;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\DimensaoQualidade;
use App\Models\DocenteProgramaVinculo;
use App\Models\EspelhoDiscente;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\Programa;
use App\Models\ProjetoPesquisa;
use App\Models\User;
use App\Services\CpaPainelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class CpaPainelAnalisesTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;
    private Banca $banca;
    private User $discente;
    private User $orientador;
    private array $avaliadores;
    private Barema $padrao;
    private Barema $confidencial;
    private BaremaItem $rigor;
    private BaremaItem $original;
    private BaremaItem $rigorConf;
    private PeriodoQualidade $periodo;
    private User $membro;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->secretaria, $this->programa, $this->banca, $this->discente, $this->orientador, , , $this->avaliadores] = $this->criarCenarioCompleto('defesa', false);
        $this->banca->trabalhoAcademico->publicacoes()->delete();
        $this->banca->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada', 'data_hora' => '2025-05-10 14:00:00']);

        $this->padrao = Barema::create(['programa_id' => $this->programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $this->confidencial = Barema::create(['programa_id' => $this->programa->id, 'nome' => 'Defesa sigilosa', 'tipo' => 'defesa_confidencial', 'nivel' => 'mestrado', 'versao' => 1, 'ativo' => true]);
        $this->rigor = $this->item($this->padrao, 'rigor', 'Clareza do método');
        $this->original = $this->item($this->padrao, 'originalidade', 'Contribuição original');
        $this->rigorConf = $this->item($this->confidencial, 'rigor', 'Rigor na visão sigilosa');
        $this->periodo = PeriodoQualidade::create(['programa_id' => $this->programa->id, 'nome' => 'Quadriênio', 'inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31']);

        $this->membro = User::factory()->docente()->create(['primeiro_acesso' => false]);
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $this->membro->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato']);
    }

    private function item(Barema $barema, string $dimensao, string $nome): BaremaItem
    {
        $secao = $barema->secoes()->firstOrCreate(['nome' => 'Seção'], ['peso' => 1, 'ordem' => 1, 'ativo' => true]);

        return BaremaItem::create(['barema_secao_id' => $secao->id, 'dimensao_qualidade_id' => DimensaoQualidade::doCodigo($dimensao)->id,
            'nome' => $nome, 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ordem' => 1, 'ativo' => true]);
    }

    /** $notas: [indice avaliador => [item => nota]] */
    private function notas(array $porAvaliador): void
    {
        $membros = $this->banca->avaliadores()->orderBy('ordem')->get()->values();
        foreach ($porAvaliador as $i => $porItem) {
            $porBarema = [];
            foreach ($porItem as [$item, $nota]) {
                $porBarema[$item->secao->barema_id][] = [$item, $nota];
            }
            foreach ($porBarema as $baremaId => $itens) {
                $avaliacao = BancaAvaliacao::create(['banca_id' => $this->banca->id, 'banca_membro_id' => $membros[$i]->id, 'barema_id' => $baremaId, 'status' => 'enviada', 'enviada_em' => now()]);
                foreach ($itens as [$item, $nota]) {
                    $avaliacao->notas()->create(['barema_item_id' => $item->id, 'dimensao_qualidade_id' => $item->dimensao_qualidade_id, 'nota' => $nota]);
                }
            }
        }
    }

    private function painel(?User $membro = null): array
    {
        return app(CpaPainelService::class)->montar($this->periodo, [], $membro ?? $this->membro);
    }

    public function test_pontos_fortes_e_fracos(): void
    {
        $this->notas([
            [[$this->rigor, 9], [$this->original, 4], [$this->rigorConf, 7]],
            [[$this->rigor, 9], [$this->original, 5], [$this->rigorConf, 7]],
        ]);

        $pontos = $this->painel()['pontos'];

        $this->assertSame('Clareza do método', $pontos['fortes'][0]['pergunta']);
        $this->assertSame('Contribuição original', $pontos['fracos'][0]['pergunta']);
        $this->assertEqualsWithDelta(4.5, $pontos['fracos'][0]['mediana'], 0.001);
    }

    public function test_concordancia_entre_avaliadores(): void
    {
        $this->notas([
            [[$this->rigor, 9], [$this->original, 4]],
            [[$this->rigor, 8], [$this->original, 8]],
        ]);

        $c = $this->painel()['confiabilidade']['concordancia'];

        // amplitudes 1 e 4 → mediana 2,5; 50% até 2 pontos
        $this->assertEqualsWithDelta(2.5, $c['mediana_amplitude'], 0.001);
        $this->assertEqualsWithDelta(50.0, $c['percentual_ate_2'], 0.001);
    }

    public function test_aberto_versus_confidencial_e_espelho_e_orientador_versus_banca(): void
    {
        $this->notas([
            [[$this->rigor, 8], [$this->rigorConf, 6]],
            [[$this->rigor, 8], [$this->rigorConf, 6]],
        ]);
        $vinculo = $this->banca->trabalhoAcademico->orientacoes()->where('tipo', 'orientador')->first()->docente_programa_vinculo_id;
        $auto = AutoavaliacaoOrientador::create(['banca_id' => $this->banca->id, 'docente_programa_vinculo_id' => $vinculo, 'barema_id' => $this->padrao->id, 'nota_final' => 9, 'enviada_em' => now()]);
        $auto->notas()->create(['barema_item_id' => $this->rigor->id, 'dimensao_qualidade_id' => $this->rigor->dimensao_qualidade_id, 'nota' => 9]);
        $espelho = EspelhoDiscente::create(['banca_id' => $this->banca->id, 'barema_id' => $this->padrao->id, 'nota_final' => 10, 'enviada_em' => now()]);
        $espelho->notas()->create(['barema_item_id' => $this->rigor->id, 'dimensao_qualidade_id' => $this->rigor->dimensao_qualidade_id, 'nota' => 10]);

        $conf = $this->painel()['confiabilidade'];

        $this->assertEqualsWithDelta(8.0, $conf['aberto_confidencial']['rigor']['aberto'], 0.001);
        $this->assertEqualsWithDelta(6.0, $conf['aberto_confidencial']['rigor']['confidencial'], 0.001);
        // banca (mediana por pergunta, padrão e sigiloso juntos): rigor = (8 + 6) / 2 = 7
        $this->assertEqualsWithDelta(3.0, $conf['espelho_banca']['rigor'], 0.001);
        $this->assertEqualsWithDelta(2.0, $conf['orientador_banca']['rigor'], 0.001);
    }

    public function test_projetos_com_objetivos_sem_nomes(): void
    {
        $vinculo = DocenteProgramaVinculo::find($this->banca->trabalhoAcademico->orientacoes()->where('tipo', 'orientador')->first()->docente_programa_vinculo_id);
        $projeto = ProjetoPesquisa::create(['docente_programa_vinculo_id' => $vinculo->id, 'titulo' => 'Projeto Arboviroses', 'descricao' => 'Objetivos do projeto', 'inicio_em' => '2024-01-01', 'situacao' => 'ativo']);
        $this->banca->trabalhoAcademico->projetoVinculos()->create(['projeto_pesquisa_id' => $projeto->id, 'motivo' => 'orientador', 'inicio_em' => '2024-02-01', 'ativo' => true, 'objetivo' => 'Avança o objetivo 2', 'objetivo_informado_em' => now()]);

        $projetos = $this->painel()['projetos'];
        $this->assertSame('Projeto Arboviroses', $projetos[0]['titulo']);
        $this->assertSame(['Avança o objetivo 2'], array_column($projetos[0]['objetivos'], 'objetivo'));

        $this->actingAs($this->membro)->get(route('cpa.index'))
            ->assertSee('Projeto Arboviroses')->assertSee('Avança o objetivo 2')
            ->assertDontSee($this->discente->name)->assertDontSee($this->orientador->name);
    }

    public function test_aviso_de_conflito_para_quem_participou(): void
    {
        $this->notas([[[$this->rigor, 8]], [[$this->rigor, 8]]]);
        $avaliador = $this->avaliadores[0];
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $avaliador->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato 2']);
        MembroCpa::create(['programa_id' => $this->programa->id, 'user_id' => $this->orientador->id, 'inicio_em' => today()->subMonth(), 'numero_ato' => 'Ato 3']);

        $this->assertSame(1, $this->painel($avaliador)['conflito']);
        $this->assertSame(1, $this->painel($this->orientador)['conflito']);
        $this->actingAs($avaliador)->get(route('cpa.index'))->assertSee('Este recorte inclui 1 trabalho(s) em que você participou');
    }

    public function test_sem_aviso_para_quem_nao_participou(): void
    {
        $this->notas([[[$this->rigor, 8]], [[$this->rigor, 8]]]);

        $this->assertSame(0, $this->painel()['conflito']);
        $this->actingAs($this->membro)->get(route('cpa.index'))->assertDontSee('em que você participou');
    }

    public function test_acesso_registrado_e_visivel_a_secretaria(): void
    {
        $this->actingAs($this->membro)->get(route('cpa.index', ['nivel' => 'mestrado']))->assertOk();

        $acesso = AcessoCpa::firstOrFail();
        $this->assertSame($this->membro->id, $acesso->user_id);
        $this->assertSame('cpa.index', $acesso->pagina);
        $this->assertSame('mestrado', $acesso->filtros['nivel']);

        $this->actingAs($this->secretaria)->get(route('membros-cpa.acessos'))->assertOk()->assertSee($this->membro->name)->assertSee('cpa.index');
    }

    public function test_secretaria_nao_abre_painel(): void
    {
        $this->actingAs($this->secretaria)->get(route('cpa.index'))->assertForbidden();
        $this->actingAs($this->membro)->get(route('membros-cpa.acessos'))->assertForbidden();
    }
}
