<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDadosPesquisa;
use App\Models\TrabalhoInformacoesAdicionais;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InformacoesAdicionaisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_salva_tudo_nao_com_originalidade(): void
    {
        [$discente, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), $this->tudoNao())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('meu-trabalho.informacoes.edit'));

        $info = TrabalhoInformacoesAdicionais::where('trabalho_academico_id', $trabalho->id)->sole();
        $this->assertFalse($info->conflito_interesses);
        $this->assertSame('nenhum_dado_novo', $info->disponibilidade_dados);
        $this->assertTrue($info->originalidade_confirmada);
        $this->assertNotNull($info->preenchido_em);
        $this->assertNull($info->conflito_descricao);
        $this->assertNull($info->ia_ferramentas);
    }

    public function test_desdobramentos_obrigatorios_quando_sim(): void
    {
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), [
            ...$this->tudoNao(),
            'conflito_interesses' => '1',
            'disponibilidade_dados' => 'repositorio_publico',
            'amostras_fisicas' => '1',
            'aprovacao_etica' => '1',
            'participantes' => '1',
            'uso_ia' => '1',
        ])->assertSessionHasErrors([
            'conflito_descricao', 'amostras_declaracao', 'aprovacao_etica_arquivo', 'certidao_etica_arquivo',
            'ia_ferramentas', 'ia_etapas', 'dados_repositorio',
        ]);

        $this->assertDatabaseCount('trabalho_informacoes_adicionais', 0);
    }

    public function test_originalidade_obrigatoria(): void
    {
        [$discente] = $this->estrutura();
        $dados = $this->tudoNao();
        unset($dados['originalidade_confirmada']);

        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), $dados)
            ->assertSessionHasErrors('originalidade_confirmada');
    }

    public function test_campos_sem_efeito_sao_ignorados_e_limpos(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), $this->tudoSim())->assertSessionHasNoErrors();
        $info = TrabalhoInformacoesAdicionais::sole();
        Storage::disk('local')->assertExists($info->aprovacao_etica_path);
        Storage::disk('local')->assertExists($info->certidao_etica_path);
        $arquivos = [$info->aprovacao_etica_path, $info->certidao_etica_path];

        // Radio não desmarca: o texto antigo continua indo depois de trocar para "Não".
        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), [
            ...$this->tudoNao(),
            'conflito_descricao' => 'Texto antigo',
            'dados_repositorio' => 'não é url',
            'amostras_declaracao' => 'Texto antigo',
            'ia_ferramentas' => 'Texto antigo',
            'ia_etapas' => 'Texto antigo',
        ])->assertSessionHasNoErrors();

        $info->refresh();
        $this->assertNull($info->conflito_descricao);
        $this->assertNull($info->dados_repositorio);
        $this->assertNull($info->amostras_declaracao);
        $this->assertNull($info->ia_ferramentas);
        $this->assertNull($info->aprovacao_etica_path);
        $this->assertNull($info->certidao_etica_path);
        foreach ($arquivos as $arquivo) {
            Storage::disk('local')->assertMissing($arquivo);
        }
    }

    public function test_envio_e_download_dos_pdfs_de_etica(): void
    {
        [$discente] = $this->estrutura();
        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), $this->tudoSim())->assertSessionHasNoErrors();
        $info = TrabalhoInformacoesAdicionais::sole();
        $this->assertStringStartsWith('informacoes-adicionais/', $info->aprovacao_etica_path);
        $this->assertSame('parecer.pdf', $info->aprovacao_etica_nome);
        $this->assertSame('certidao.pdf', $info->certidao_etica_nome);

        $this->actingAs($discente)->get(route('meu-trabalho.informacoes.etica', ['documento' => 'aprovacao']))->assertOk();
        $this->actingAs($discente)->get(route('meu-trabalho.informacoes.etica', ['documento' => 'certidao']))->assertOk();
        $this->actingAs($discente)->get(route('meu-trabalho.informacoes.etica', ['documento' => 'outro']))->assertNotFound();

        // Reenviar sem arquivo mantém o já gravado; arquivo que não é PDF é recusado.
        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), [
            ...$this->tudoSim(), 'aprovacao_etica_arquivo' => null, 'certidao_etica_arquivo' => null,
        ])->assertSessionHasNoErrors();
        $this->assertSame($info->aprovacao_etica_path, $info->fresh()->aprovacao_etica_path);

        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), [
            ...$this->tudoSim(), 'aprovacao_etica_arquivo' => UploadedFile::fake()->create('parecer.docx', 10, 'application/msword'),
        ])->assertSessionHasErrors('aprovacao_etica_arquivo');
    }

    public function test_pre_marca_a_partir_dos_dados_da_pesquisa(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        TrabalhoDadosPesquisa::create([
            'trabalho_academico_id' => $trabalho->id, 'codigo' => 'nao_houve', 'dados_tipos' => ['primarios'],
            'dados_disponibilidade' => 'publico', 'dados_link' => 'https://doi.org/10.5281/zenodo.123',
            'seres_humanos' => true, 'caae' => '12345678.9.0000.0057', 'parecer_numero' => '1', 'tcle' => 'termo',
            'animais' => false, 'preenchido_em' => now(),
        ]);

        $this->actingAs($discente)->get(route('meu-trabalho.informacoes.edit'))
            ->assertOk()
            ->assertSee('Você tem algum potencial conflito de interesses a declarar?')
            ->assertSee('id="aprovacao_etica-1" value="1" checked', false)
            ->assertSee('id="participantes-1" value="1" checked', false)
            ->assertSee('id="disponibilidade-repositorio_publico" value="repositorio_publico" checked', false)
            ->assertSee('https://doi.org/10.5281/zenodo.123');
    }

    public function test_trava_com_pedido_de_defesa_aguardando(): void
    {
        [$discente, $trabalho] = $this->estrutura();
        Banca::create([
            'trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa', 'status' => 'cadastrada',
            'homologacao_status' => 'aguardando', 'homologacao_tentativa' => 1, 'libera_envio_trabalho' => false,
        ]);

        $this->actingAs($discente)->put(route('meu-trabalho.informacoes.update'), $this->tudoNao())
            ->assertSessionHasErrors('travado');
        $this->assertDatabaseCount('trabalho_informacoes_adicionais', 0);
    }

    public function test_outro_perfil_nao_acessa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('meu-trabalho.informacoes.edit'))->assertForbidden();
        $this->actingAs($docente)->put(route('meu-trabalho.informacoes.update'), $this->tudoNao())->assertForbidden();
    }

    private function tudoNao(): array
    {
        return [
            'conflito_interesses' => '0',
            'disponibilidade_dados' => 'nenhum_dado_novo',
            'amostras_fisicas' => '0',
            'aprovacao_etica' => '0',
            'participantes' => '0',
            'uso_ia' => '0',
            'originalidade_confirmada' => '1',
        ];
    }

    private function tudoSim(): array
    {
        return [
            'conflito_interesses' => '1',
            'conflito_descricao' => 'Bolsa de empresa parceira.',
            'disponibilidade_dados' => 'repositorio_publico',
            'dados_repositorio' => 'https://doi.org/10.5281/zenodo.123',
            'amostras_fisicas' => '1',
            'amostras_declaracao' => 'Amostras no biobanco da UNEB, sob solicitação.',
            'aprovacao_etica' => '1',
            'aprovacao_etica_arquivo' => UploadedFile::fake()->create('parecer.pdf', 100, 'application/pdf'),
            'participantes' => '1',
            'certidao_etica_arquivo' => UploadedFile::fake()->create('certidao.pdf', 100, 'application/pdf'),
            'uso_ia' => '1',
            'ia_ferramentas' => 'Claude',
            'ia_etapas' => 'Revisão de texto da introdução.',
            'originalidade_confirmada' => '1',
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
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);

        return [$discente, $trabalho];
    }
}
