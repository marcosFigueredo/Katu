<?php

namespace Tests\Feature;

use App\Models\AtividadeFormativa;
use App\Models\AtividadeRealizada;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AtividadesFormativasTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_ve_as_quatro_atividades_iniciais_e_cadastra_outra(): void
    {
        [$secretaria, $programa] = $this->estrutura();

        $this->actingAs($secretaria)->get(route('atividades-formativas.index'))
            ->assertOk()
            ->assertSee('Proficiência em Língua Estrangeira')
            ->assertSee('Publicação de Produção Científica')
            ->assertSee('Atividade Extensionista')
            ->assertSee('Popularização das Ciências');
        $this->assertSame(4, AtividadeFormativa::where('programa_id', $programa->id)->count());
        $this->assertSame('secretaria', AtividadeFormativa::where('nome', 'Proficiência em Língua Estrangeira')->value('informada_por'));

        $this->actingAs($secretaria)->post(route('atividades-formativas.store'), [
            'nome' => 'Estágio docência',
            'detalhes' => 'Um semestre em disciplina da graduação.',
            'quantidade_exigida' => 2,
            'etapa' => 'defesa',
            'informada_por' => 'estudante',
        ])->assertSessionHasNoErrors();

        $nova = AtividadeFormativa::where('nome', 'Estágio docência')->sole();
        $this->assertSame($programa->id, $nova->programa_id);
        $this->assertSame(2, $nova->quantidade_exigida);

        $docente = User::factory()->docente()->create();
        $this->actingAs($docente)->get(route('atividades-formativas.index'))->assertForbidden();

        [, $outroPrograma] = $this->estrutura('PPGX');
        $alheia = AtividadeFormativa::create([
            'programa_id' => $outroPrograma->id, 'nome' => 'Alheia', 'quantidade_exigida' => 1,
            'etapa' => 'defesa', 'informada_por' => 'estudante', 'ordem' => 1,
        ]);
        $this->actingAs($secretaria)->put(route('atividades-formativas.update', $alheia), [
            'nome' => 'Tomada', 'quantidade_exigida' => 1, 'etapa' => 'defesa', 'informada_por' => 'estudante',
        ])->assertForbidden();
        $this->assertSame('Alheia', $alheia->fresh()->nome);
    }

    public function test_programa_novo_ja_exige_as_atividades_padrao_sem_visita_da_secretaria(): void
    {
        [, , $discente, $trabalho] = $this->estrutura();

        $faltantes = app(\App\Services\AtividadesFormativasService::class)->faltantes($trabalho, 'defesa');
        $this->assertContains('Popularização das Ciências (0 de 1)', $faltantes);

        $this->actingAs($discente)->get(route('meu-trabalho.atividades.index'))
            ->assertSee('Atividade Extensionista');
    }

    public function test_secretaria_altera_quantidade_e_inativa(): void
    {
        [$secretaria, $programa, $discente] = $this->estrutura();
        AtividadeFormativa::criarPadroes($programa);
        $extensao = AtividadeFormativa::where('nome', 'Atividade Extensionista')->sole();

        $this->actingAs($secretaria)->put(route('atividades-formativas.update', $extensao), [
            'nome' => 'Atividade Extensionista', 'quantidade_exigida' => 0, 'etapa' => 'defesa', 'informada_por' => 'estudante',
        ])->assertSessionHasErrors('quantidade_exigida');

        $this->actingAs($secretaria)->post(route('atividades-formativas.inativar', $extensao))->assertRedirect();
        $this->assertFalse($extensao->fresh()->ativo);

        $this->actingAs($discente)->get(route('meu-trabalho.atividades.index'))
            ->assertOk()
            ->assertDontSee('Atividade Extensionista');
    }

    public function test_estudante_ve_so_as_que_ele_informa_e_lanca_realizacao(): void
    {
        [, $programa, $discente, $trabalho] = $this->estrutura();
        AtividadeFormativa::criarPadroes($programa);
        $extensao = AtividadeFormativa::where('nome', 'Atividade Extensionista')->sole();
        $proficiencia = AtividadeFormativa::where('nome', 'Proficiência em Língua Estrangeira')->sole();

        $this->actingAs($discente)->get(route('meu-trabalho.atividades.index'))
            ->assertOk()
            ->assertSee('Atividade Extensionista')
            ->assertSee('0 de 1')
            ->assertSee('Informada pela Secretaria');

        $this->actingAs($discente)->post(route('meu-trabalho.atividades.store'), [
            'atividade_formativa_id' => $extensao->id,
            'descricao' => 'Oficina de modelagem na escola estadual.',
            'link' => 'https://proex.uneb.br/registro/123',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, AtividadeRealizada::where('trabalho_academico_id', $trabalho->id)->count());

        $this->actingAs($discente)->post(route('meu-trabalho.atividades.store'), [
            'atividade_formativa_id' => $extensao->id, 'descricao' => 'X', 'link' => 'abc',
        ])->assertSessionHasErrors('link');

        $this->actingAs($discente)->post(route('meu-trabalho.atividades.store'), [
            'atividade_formativa_id' => $proficiencia->id, 'descricao' => 'TOEFL',
        ])->assertSessionHasErrors('atividade_formativa_id');

        [, $outroPrograma] = $this->estrutura('PPGX');
        $alheia = AtividadeFormativa::create([
            'programa_id' => $outroPrograma->id, 'nome' => 'Alheia', 'quantidade_exigida' => 1,
            'etapa' => 'defesa', 'informada_por' => 'estudante', 'ordem' => 1,
        ]);
        $this->actingAs($discente)->post(route('meu-trabalho.atividades.store'), [
            'atividade_formativa_id' => $alheia->id, 'descricao' => 'X',
        ])->assertSessionHasErrors('atividade_formativa_id');

        $this->assertSame(1, AtividadeRealizada::count());
    }

    public function test_estudante_exclui_lancamento_nao_confirmado(): void
    {
        [, $programa, $discente, $trabalho] = $this->estrutura();
        AtividadeFormativa::criarPadroes($programa);
        $extensao = AtividadeFormativa::where('nome', 'Atividade Extensionista')->sole();
        $livre = $this->lancamento($trabalho, $extensao);
        $confirmado = $this->lancamento($trabalho, $extensao, 'confirmada');

        $this->actingAs($discente)->delete(route('meu-trabalho.atividades.destroy', $livre))->assertSessionHasNoErrors();
        $this->assertModelMissing($livre);

        $this->actingAs($discente)->delete(route('meu-trabalho.atividades.destroy', $confirmado))->assertSessionHasErrors('atividade');
        $this->assertModelExists($confirmado);

        [, , , $outroTrabalho] = $this->estrutura('PPGX');
        $alheio = $this->lancamento($outroTrabalho, $extensao);
        $this->actingAs($discente)->delete(route('meu-trabalho.atividades.destroy', $alheio))->assertNotFound();
        $this->assertModelExists($alheio);
    }

    public function test_trava_com_pedido_aguardando(): void
    {
        [, $programa, $discente, $trabalho] = $this->estrutura();
        AtividadeFormativa::criarPadroes($programa);
        $extensao = AtividadeFormativa::where('nome', 'Atividade Extensionista')->sole();
        $lancamento = $this->lancamento($trabalho, $extensao);
        Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao', 'status' => 'cadastrada',
            'homologacao_status' => 'aguardando', 'homologacao_tentativa' => 1, 'libera_envio_trabalho' => false,
        ]);

        $this->actingAs($discente)->post(route('meu-trabalho.atividades.store'), [
            'atividade_formativa_id' => $extensao->id, 'descricao' => 'Nova',
        ])->assertSessionHasErrors('travado');
        $this->actingAs($discente)->delete(route('meu-trabalho.atividades.destroy', $lancamento))->assertSessionHasErrors('travado');

        $this->assertSame(1, AtividadeRealizada::count());
    }

    private function lancamento(TrabalhoAcademico $trabalho, AtividadeFormativa $atividade, ?string $conferencia = null): AtividadeRealizada
    {
        return AtividadeRealizada::create([
            'trabalho_academico_id' => $trabalho->id,
            'atividade_formativa_id' => $atividade->id,
            'descricao' => 'Oficina',
            'conferencia' => $conferencia,
        ]);
    }

    /** @return array{0: User, 1: Programa, 2: User, 3: TrabalhoAcademico} */
    private function estrutura(string $sigla = 'PPGMSB'): array
    {
        $instituicao = Instituicao::firstOrCreate(['sigla' => 'UNEB'], ['nome' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => $sigla, 'sigla' => $sigla, 'instituicao_id' => $instituicao->id]);
        $secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id, 'user_id' => $secretaria->id, 'categoria_no_programa' => 'secretaria',
            'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);

        return [$secretaria, $programa, $discente, $trabalho];
    }
}
