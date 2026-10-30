<?php

namespace Tests\Feature;

use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\DimensaoQualidade;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Database\Seeders\DimensoesQualidadeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CriaCenarioDeBanca;
use Tests\TestCase;

class DimensoesQualidadeTest extends TestCase
{
    use CriaCenarioDeBanca;
    use RefreshDatabase;

    private User $sec;
    private Barema $barema;
    private BaremaSecao $secao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DimensoesQualidadeSeeder::class);

        $this->sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($this->sec, $programa);
        $this->barema = Barema::create(['programa_id' => $programa->id, 'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'ambos', 'versao' => '1', 'ativo' => false]);
        $this->secao = BaremaSecao::create(['barema_id' => $this->barema->id, 'nome' => 'Seção', 'peso' => 1, 'ordem' => 1, 'ativo' => true]);
    }

    private function item(?string $dimensao, bool $ativo = true): BaremaItem
    {
        return BaremaItem::create([
            'barema_secao_id' => $this->secao->id, 'nome' => 'Pergunta '.($dimensao ?? 'sem'), 'nota_maxima' => 10, 'peso' => 1,
            'obrigatorio' => true, 'ordem' => 1, 'ativo' => $ativo,
            'dimensao_qualidade_id' => $dimensao ? DimensaoQualidade::doCodigo($dimensao)->id : null,
        ]);
    }

    private function ativar()
    {
        return $this->actingAs($this->sec)->put(route('baremas.update', $this->barema), [
            'nome' => 'Defesa', 'tipo' => 'defesa', 'nivel' => 'ambos', 'versao' => '1', 'ativo' => '1',
        ]);
    }

    public function test_seeder_cria_as_8_dimensoes_na_ordem(): void
    {
        $this->seed(DimensoesQualidadeSeeder::class); // idempotente

        $this->assertSame([
            'Rigor científico', 'Originalidade', 'Relevância e impacto', 'Produção associada',
            'Autonomia do estudante', 'Aderência ao programa', 'Processo formativo', 'Integridade e conformidade',
        ], DimensaoQualidade::orderBy('ordem')->pluck('nome')->all());
        $this->assertSame('rigor', DimensaoQualidade::orderBy('ordem')->value('codigo'));
    }

    public function test_incluir_ou_editar_pergunta_exige_dimensao(): void
    {
        $dados = ['nome' => 'Método', 'nota_maxima' => 10, 'peso' => 1, 'ordem' => 1];

        $this->actingAs($this->sec)->post(route('barema-secoes.itens.store', $this->secao), $dados)
            ->assertSessionHasErrors(['dimensao_qualidade_id' => 'Escolha a dimensão da pergunta.']);

        $rigor = DimensaoQualidade::doCodigo('rigor');
        $this->actingAs($this->sec)->post(route('barema-secoes.itens.store', $this->secao), $dados + ['dimensao_qualidade_id' => $rigor->id])
            ->assertSessionHasNoErrors();
        $item = BaremaItem::where('nome', 'Método')->firstOrFail();
        $this->assertSame($rigor->id, $item->dimensao_qualidade_id);

        $this->actingAs($this->sec)->put(route('barema-itens.update', $item), $dados + ['dimensao_qualidade_id' => ''])
            ->assertSessionHasErrors('dimensao_qualidade_id');
        $this->assertSame($rigor->id, $item->fresh()->dimensao_qualidade_id);
    }

    public function test_ativar_barema_exige_dimensao_em_todas_as_perguntas_ativas(): void
    {
        $this->item('rigor');
        $this->item(null);

        $this->ativar()->assertSessionHasErrors([
            'ativo' => 'Todas as perguntas ativas precisam de uma dimensão antes de ativar o barema.',
        ]);
        $this->assertFalse($this->barema->fresh()->ativo);
    }

    public function test_pergunta_inativa_sem_dimensao_nao_impede_ativar(): void
    {
        $this->item('rigor');
        $this->item(null, ativo: false);

        $this->ativar()->assertSessionHasNoErrors();
        $this->assertTrue($this->barema->fresh()->ativo);
    }

    public function test_tela_do_barema_mostra_a_dimensao_de_cada_pergunta(): void
    {
        $this->item('originalidade');

        $this->actingAs($this->sec)->get(route('baremas.edit', $this->barema))
            ->assertOk()
            ->assertSee('Originalidade')
            ->assertSee('name="dimensao_qualidade_id"', false);
    }

    public function test_homologacao_recusa_barema_ativo_com_pergunta_sem_dimensao(): void
    {
        Mail::fake();
        [$secretaria, , $banca] = $this->criarCenarioCompleto('qualificacao');
        BaremaItem::whereHas('secao.barema', fn ($q) => $q->where('tipo', 'qualificacao_confidencial'))
            ->update(['dimensao_qualidade_id' => null]);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors(['homologacao' => 'Barema de Qualificação Confidencial com perguntas sem dimensão.']);
    }
}
