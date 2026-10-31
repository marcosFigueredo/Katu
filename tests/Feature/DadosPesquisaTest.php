<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDadosPesquisa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DadosPesquisaTest extends TestCase
{
    use RefreshDatabase;

    public function test_discente_abre_a_tela_dos_dados_da_pesquisa(): void
    {
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->get(route('meu-trabalho.dados-pesquisa.edit'))
            ->assertOk()
            ->assertSee('Dados da pesquisa')
            ->assertSee('CAAE');
    }

    public function test_discente_salva_o_caso_completo(): void
    {
        [$discente, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->put(route('meu-trabalho.dados-pesquisa.update'), $this->completo())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('meu-trabalho.dados-pesquisa.edit'));

        $dados = TrabalhoDadosPesquisa::where('trabalho_academico_id', $trabalho->id)->firstOrFail();
        $this->assertSame('publico', $dados->codigo);
        $this->assertSame('https://github.com/juliana/modelo', $dados->codigo_link);
        $this->assertSame(['primarios', 'simulados'], $dados->dados_tipos);
        $this->assertSame('publico', $dados->dados_disponibilidade);
        $this->assertSame('https://doi.org/10.5281/zenodo.123', $dados->dados_link);
        $this->assertTrue($dados->seres_humanos);
        $this->assertSame('12345678.9.0000.0057', $dados->caae);
        $this->assertSame('6.543.210', $dados->parecer_numero);
        $this->assertSame('termo', $dados->tcle);
        $this->assertTrue($dados->animais);
        $this->assertSame('CEUA 012/2025', $dados->ceua_protocolo);
        $this->assertNotNull($dados->preenchido_em);
    }

    public function test_respostas_nao_limpam_os_campos_que_deixaram_de_valer(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        $this->actingAs($discente)->put(route('meu-trabalho.dados-pesquisa.update'), $this->completo());

        $this->actingAs($discente)->put(route('meu-trabalho.dados-pesquisa.update'), [
            ...$this->completo(),
            'codigo' => 'sob_solicitacao',
            'dados_disponibilidade' => 'nao_se_aplica',
            'dados_tipos' => [],
            'seres_humanos' => '0',
            'animais' => '0',
        ])->assertSessionHasNoErrors();

        $dados = TrabalhoDadosPesquisa::where('trabalho_academico_id', $trabalho->id)->sole();
        $this->assertNull($dados->codigo_link);
        $this->assertSame([], $dados->dados_tipos);
        $this->assertNull($dados->dados_link);
        $this->assertNull($dados->caae);
        $this->assertNull($dados->parecer_numero);
        $this->assertNull($dados->tcle);
        $this->assertNull($dados->ceua_protocolo);
    }

    public function test_validacoes_condicionais(): void
    {
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->put(route('meu-trabalho.dados-pesquisa.update'), [
            ...$this->completo(),
            'codigo_link' => '',
            'dados_link' => '',
            'caae' => '',
            'parecer_numero' => '',
            'tcle' => '',
            'ceua_protocolo' => '',
        ])->assertSessionHasErrors(['codigo_link', 'dados_link', 'caae', 'parecer_numero', 'tcle', 'ceua_protocolo']);

        $this->actingAs($discente)->put(route('meu-trabalho.dados-pesquisa.update'), [
            ...$this->completo(),
            'codigo' => 'nao_compartilhavel',
            'dados_disponibilidade' => 'nao_compartilhavel',
            'dados_tipos' => [],
            'tcle' => 'dispensa',
            'caae' => '123',
        ])->assertSessionHasErrors(['codigo_justificativa', 'dados_justificativa', 'dados_tipos', 'tcle_justificativa', 'caae']);

        $this->assertDatabaseCount('trabalho_dados_pesquisa', 0);
    }

    public function test_outro_perfil_nao_acessa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('meu-trabalho.dados-pesquisa.edit'))->assertForbidden();
        $this->actingAs($docente)->put(route('meu-trabalho.dados-pesquisa.update'), $this->completo())->assertForbidden();
    }

    private function completo(): array
    {
        return [
            'codigo' => 'publico',
            'codigo_link' => 'https://github.com/juliana/modelo',
            'codigo_justificativa' => '',
            'dados_tipos' => ['primarios', 'simulados'],
            'dados_disponibilidade' => 'publico',
            'dados_link' => 'https://doi.org/10.5281/zenodo.123',
            'dados_justificativa' => '',
            'seres_humanos' => '1',
            'caae' => '12345678.9.0000.0057',
            'parecer_numero' => '6.543.210',
            'tcle' => 'termo',
            'tcle_justificativa' => '',
            'animais' => '1',
            'ceua_protocolo' => 'CEUA 012/2025',
        ];
    }

    /** @return array{0: User, 1: TrabalhoAcademico} */
    private function estrutura(): array
    {
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);

        return [$discente, $trabalho];
    }
}
