<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\OrientacaoService;
use App\Services\ProjetoPesquisaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjetoPesquisaServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;
    private ProjetoPesquisaService $projetos;
    private OrientacaoService $orientacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id, 'max_coorientadores' => 2]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);

        $this->projetos = app(ProjetoPesquisaService::class);
        $this->orientacao = app(OrientacaoService::class);
    }

    private function docente(string $categoria = 'permanente'): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create(['user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => 'UNEB', 'ativo' => true]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id, 'tipo_vinculo' => 'interno',
            'categoria_docente' => $categoria, 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function trabalho(): TrabalhoAcademico
    {
        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);

        return TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function projeto(DocenteProgramaVinculo $docente, string $titulo = 'Projeto A')
    {
        return $this->projetos->cadastrar($docente, [
            'titulo' => $titulo, 'descricao' => "Descrição de {$titulo}", 'inicio_em' => '2025-03-01',
        ], $docente->docenteProfile->user);
    }

    public function test_cadastrar_conclui_o_anterior_e_deixa_um_so_ativo(): void
    {
        $docente = $this->docente();
        $primeiro = $this->projeto($docente, 'Projeto A');
        $segundo = $this->projeto($docente, 'Projeto B');

        $this->assertSame('concluido', $primeiro->fresh()->situacao);
        $this->assertSame(today()->toDateString(), $primeiro->fresh()->fim_em->toDateString());
        $this->assertSame('ativo', $segundo->fresh()->situacao);
        $this->assertTrue($docente->projetoAtivo()->is($segundo));
    }

    public function test_so_docente_permanente_interno_tem_projeto(): void
    {
        $this->expectException(ValidationException::class);
        $this->projeto($this->docente('colaborador'));
    }

    public function test_definir_orientador_vincula_ao_projeto_ativo(): void
    {
        $docente = $this->docente();
        $projeto = $this->projeto($docente);
        $trabalho = $this->trabalho();

        $this->orientacao->definirOrientadorInicial($trabalho, $docente->id, $this->secretaria);

        $vinculo = $trabalho->projetoVinculoVigente();
        $this->assertNotNull($vinculo);
        $this->assertSame($projeto->id, $vinculo->projeto_pesquisa_id);
        $this->assertSame('orientador', $vinculo->motivo);
        $this->assertNull($vinculo->objetivo);
    }

    public function test_orientador_sem_projeto_deixa_estudante_sem_vinculo(): void
    {
        $trabalho = $this->trabalho();
        $this->orientacao->definirOrientadorInicial($trabalho, $this->docente()->id, $this->secretaria);

        $this->assertNull($trabalho->projetoVinculoVigente());
        $this->assertTrue($this->projetos->projetoExigido($trabalho));
    }

    public function test_novo_projeto_revincula_orientandos_e_guarda_historico(): void
    {
        $docente = $this->docente();
        $this->projeto($docente, 'Projeto A');
        $trabalho = $this->trabalho();
        $this->orientacao->definirOrientadorInicial($trabalho, $docente->id, $this->secretaria);
        $trabalho->projetoVinculoVigente()->update(['objetivo' => 'Objetivo X', 'objetivo_informado_em' => now()]);

        $coorientando = $this->trabalho();
        $this->orientacao->definirOrientadorInicial($coorientando, $this->docente()->id, $this->secretaria);
        $this->orientacao->incluirCoorientador($coorientando, $docente->id, $this->secretaria);

        $novo = $this->projeto($docente, 'Projeto B');

        $vigente = $trabalho->projetoVinculoVigente();
        $this->assertSame($novo->id, $vigente->projeto_pesquisa_id);
        $this->assertSame('novo_projeto', $vigente->motivo);
        $this->assertNull($vigente->objetivo);
        $this->assertSame(2, $trabalho->projetoVinculos()->count());
        $this->assertSame('Objetivo X', $this->projetos->objetivoAnterior($trabalho));
        $this->assertNull($coorientando->projetoVinculoVigente());
    }

    public function test_troca_para_orientador_sem_projeto_encerra_vinculo(): void
    {
        $docente = $this->docente();
        $this->projeto($docente);
        $trabalho = $this->trabalho();
        $this->orientacao->definirOrientadorInicial($trabalho, $docente->id, $this->secretaria);

        $this->orientacao->trocarOrientador($trabalho, $this->docente('colaborador')->id, '123', 'Motivo', $this->secretaria);

        $this->assertNull($trabalho->projetoVinculoVigente());
        $this->assertSame(1, $trabalho->projetoVinculos()->count());
        $this->assertFalse($this->projetos->projetoExigido($trabalho));
    }

    public function test_troca_de_orientador_vincula_ao_projeto_do_novo(): void
    {
        $antigo = $this->docente();
        $this->projeto($antigo, 'Projeto A');
        $novoDocente = $this->docente();
        $projetoNovo = $this->projeto($novoDocente, 'Projeto B');
        $trabalho = $this->trabalho();
        $this->orientacao->definirOrientadorInicial($trabalho, $antigo->id, $this->secretaria);

        $this->orientacao->trocarOrientador($trabalho, $novoDocente->id, '123', 'Motivo', $this->secretaria);

        $vigente = $trabalho->projetoVinculoVigente();
        $this->assertSame($projetoNovo->id, $vigente->projeto_pesquisa_id);
        $this->assertSame('troca_orientador', $vigente->motivo);
    }
}
