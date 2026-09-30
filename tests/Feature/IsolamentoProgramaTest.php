<?php

namespace Tests\Feature;

use App\Models\AreaConcentracao;
use App\Models\Banca;
use App\Models\Barema;
use App\Models\BaremaSecao;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\LinhaPesquisa;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsolamentoProgramaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{secretaria: User, programa: Programa, discente: User, trabalho: TrabalhoAcademico, area: AreaConcentracao, linha: LinhaPesquisa, banca: Banca, barema: Barema} */
    private function programa(string $sufixo): array
    {
        $secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $programa = Programa::create(['nome' => "Programa $sufixo"]);
        app(ContextoSecretaria::class)->vincular($secretaria, $programa);

        $discente = User::factory()->discente()->create(['name' => "Discente $sufixo", 'email' => "discente$sufixo@exemplo.com"]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
        $area = AreaConcentracao::create(['programa_id' => $programa->id, 'nome' => "Área $sufixo", 'ativo' => true]);
        $linha = LinhaPesquisa::create(['area_concentracao_id' => $area->id, 'nome' => "Linha $sufixo", 'ativo' => true]);
        $banca = Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao',
            'status' => 'cadastrada', 'libera_envio_trabalho' => true,
        ]);
        $barema = Barema::create([
            'programa_id' => $programa->id, 'nome' => "Barema $sufixo", 'tipo' => 'qualificacao',
            'nivel' => 'ambos', 'versao' => '1.0', 'ativo' => true,
        ]);

        return compact('secretaria', 'programa', 'discente', 'trabalho', 'area', 'linha', 'banca', 'barema');
    }

    public function test_lista_de_usuarios_mostra_apenas_o_programa_da_secretaria(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');

        $this->actingAs($a['secretaria'])->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee($a['discente']->email)
            ->assertDontSee($b['discente']->email);
    }

    public function test_secretaria_nao_edita_usuario_de_outro_programa(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');

        $this->actingAs($a['secretaria'])->get(route('usuarios.edit', $b['discente']))->assertForbidden();
        $this->actingAs($a['secretaria'])->put(route('usuarios.update', $b['discente']), ['name' => 'X'])->assertForbidden();
        $this->actingAs($a['secretaria'])->post(route('usuarios.reenviar-acesso', $b['discente']))->assertForbidden();
    }

    public function test_secretaria_nao_acessa_areas_e_linhas_de_outro_programa(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');
        $sec = $a['secretaria'];

        $this->actingAs($sec)->get(route('programas.areas.index', $b['programa']))->assertForbidden();
        $this->actingAs($sec)->get(route('areas-concentracao.edit', $b['area']))->assertForbidden();
        $this->actingAs($sec)->get(route('areas.linhas.index', $b['area']))->assertForbidden();
        $this->actingAs($sec)->get(route('linhas-pesquisa.edit', $b['linha']))->assertForbidden();
        $this->actingAs($sec)->delete(route('linhas-pesquisa.destroy', $b['linha']))->assertForbidden();
        $this->assertDatabaseHas('linhas_pesquisa', ['id' => $b['linha']->id]);
    }

    public function test_bancas_e_orientacoes_listadas_sao_do_programa_da_secretaria(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');

        $this->actingAs($a['secretaria'])->get(route('bancas.index'))
            ->assertOk()->assertSee('Discente A')->assertDontSee('Discente B');
        $this->actingAs($a['secretaria'])->get(route('orientacoes.index'))
            ->assertOk()->assertSee('Discente A')->assertDontSee('Discente B');
    }

    public function test_secretaria_nao_altera_banca_ou_orientacao_de_outro_programa(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');
        $sec = $a['secretaria'];

        $this->actingAs($sec)->get(route('bancas.edit', $b['banca']))->assertForbidden();
        $this->actingAs($sec)->delete(route('bancas.destroy', $b['banca']))->assertForbidden();
        $this->actingAs($sec)->get(route('orientacoes.edit', $b['trabalho']))->assertForbidden();
        $this->assertDatabaseHas('bancas', ['id' => $b['banca']->id]);
    }

    public function test_baremas_sao_isolados_por_programa(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');
        $sec = $a['secretaria'];
        $secao = BaremaSecao::create(['barema_id' => $b['barema']->id, 'nome' => 'Seção B', 'ordem' => 1]);

        $this->actingAs($sec)->get(route('baremas.index'))
            ->assertOk()->assertSee('Barema A')->assertDontSee('Barema B');
        $this->actingAs($sec)->get(route('baremas.edit', $b['barema']))->assertForbidden();
        $this->actingAs($sec)->delete(route('baremas.destroy', $b['barema']))->assertForbidden();
        $this->actingAs($sec)->delete(route('barema-secoes.destroy', $secao))->assertForbidden();
        $this->assertDatabaseHas('baremas', ['id' => $b['barema']->id]);
    }

    public function test_dashboard_conta_apenas_o_programa(): void
    {
        $a = $this->programa('A');
        $this->programa('B');

        $this->actingAs($a['secretaria'])->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalDiscentes', 1);
    }

    public function test_secretaria_nao_ve_ata_nem_notas_de_banca_de_outro_programa(): void
    {
        $a = $this->programa('A');
        $b = $this->programa('B');
        $sec = $a['secretaria'];
        $b['banca']->update(['status' => 'finalizada', 'resultado' => 'aprovado']);

        $this->actingAs($sec)->get(route('avaliacoes.ata', $b['banca']))->assertForbidden();
        $this->actingAs($sec)->get(route('avaliacoes.relatorio-consolidado', $b['banca']))->assertForbidden();
        $this->actingAs($sec)->get(route('avaliacoes.folha-aprovacao', $b['banca']))->assertForbidden();
        $this->actingAs($sec)->get(route('avaliacoes.relatorio-individual', [$b['banca'], $b['barema']]))->assertForbidden();
    }

    public function test_secretaria_gerencia_areas_e_linhas_do_proprio_programa(): void
    {
        $a = $this->programa('A');
        $sec = $a['secretaria'];

        $this->actingAs($sec)->get(route('programas.areas.index', $a['programa']))->assertOk()->assertSee('Área A');
        $this->actingAs($sec)->post(route('programas.areas.store', $a['programa']), ['nome' => 'Área Nova'])->assertRedirect();
        $this->assertDatabaseHas('areas_concentracao', ['programa_id' => $a['programa']->id, 'nome' => 'Área Nova']);
        $this->actingAs($sec)->get(route('areas.linhas.index', $a['area']))->assertOk()->assertSee('Linha A');
        $this->actingAs($sec)->get(route('areas.linhas.create', $a['area']))->assertOk();
    }
}
