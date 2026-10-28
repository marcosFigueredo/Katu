<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\OrientacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrientacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;
    private TrabalhoAcademico $trabalho;
    private OrientacaoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id, 'max_coorientadores' => 2]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);

        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create()->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);

        $this->service = app(OrientacaoService::class);
    }

    private function docente(string $nome, string $tipo = 'interno', bool $podeOrientar = true, ?Programa $programa = null): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create(['name' => $nome])->id,
            'instituicao_sigla' => $tipo === 'externo' ? 'UFBA' : 'UNEB', 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => ($programa ?? $this->programa)->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo, 'pode_orientar' => $podeOrientar, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function esperaErro(string $campo, callable $acao, ?string $mensagem = null): void
    {
        try {
            $acao();
            $this->fail("Esperava ValidationException em {$campo}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
            if ($mensagem !== null) {
                $this->assertStringContainsString($mensagem, $e->errors()[$campo][0]);
            }
        }
    }

    public function test_define_orientador_inicial_com_data_de_hoje(): void
    {
        $docente = $this->docente('Orientadora');

        $orientacao = $this->service->definirOrientadorInicial($this->trabalho, $docente->id, $this->secretaria);

        $orientacao->refresh();
        $this->assertTrue($orientacao->inicio_em->isSameDay(today()));
        $this->assertNull($orientacao->fim_em);
        $this->assertSame($this->secretaria->id, $orientacao->registrado_por);
        $this->assertTrue($orientacao->ativo);
        $this->assertSame('orientador', $orientacao->tipo);
    }

    public function test_nao_define_inicial_quando_ja_ha_orientador(): void
    {
        $this->service->definirOrientadorInicial($this->trabalho, $this->docente('A')->id, $this->secretaria);
        $outro = $this->docente('B');

        $this->esperaErro('orientador_vinculo_id', fn () => $this->service->definirOrientadorInicial($this->trabalho, $outro->id, $this->secretaria),
            'O discente já tem orientador. Use a troca de orientador.');
    }

    public function test_orientador_inelegivel_e_recusado(): void
    {
        $semPermissao = $this->docente('Sem permissão', 'interno', false);
        $outroPrograma = $this->docente('Outro programa', 'interno', true, Programa::create(['nome' => 'Outro']));

        foreach ([$semPermissao, $outroPrograma] as $docente) {
            $this->esperaErro('orientador_vinculo_id', fn () => $this->service->definirOrientadorInicial($this->trabalho, $docente->id, $this->secretaria),
                'O orientador deve ser docente ativo do programa com permissão de orientação.');
        }
    }

    public function test_troca_encerra_o_anterior_e_preserva_o_periodo(): void
    {
        $antiga = $this->service->definirOrientadorInicial($this->trabalho, $this->docente('A')->id, $this->secretaria);
        $novoDocente = $this->docente('B');

        $nova = $this->service->trocarOrientador($this->trabalho, $novoDocente->id, '123.456/2026', 'Afastamento da orientadora.', $this->secretaria);

        $historico = $this->trabalho->orientacoesHistorico()->get();
        $this->assertCount(2, $historico);

        $antiga->refresh();
        $this->assertFalse($antiga->ativo);
        $this->assertTrue($antiga->fim_em->isSameDay(today()));

        $nova->refresh();
        $this->assertSame('123.456/2026', $nova->processo_sei);
        $this->assertSame('Afastamento da orientadora.', $nova->justificativa);
        $this->assertTrue($nova->inicio_em->isSameDay(today()));
        $this->assertSame($this->secretaria->id, $nova->registrado_por);

        $this->assertSame($nova->id, $this->trabalho->fresh()->orientadorVigente()?->id);
        $this->assertFalse($this->trabalho->fresh()->orientacoes->contains('id', $antiga->id));
    }

    public function test_troca_exige_sei_e_justificativa(): void
    {
        $antiga = $this->service->definirOrientadorInicial($this->trabalho, $this->docente('A')->id, $this->secretaria);
        $novo = $this->docente('B');

        try {
            $this->service->trocarOrientador($this->trabalho, $novo->id, '  ', '', $this->secretaria);
            $this->fail('Esperava ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('Informe o número do processo SEI que validou a troca.', $e->errors()['processo_sei'][0]);
            $this->assertSame('Informe a justificativa da troca.', $e->errors()['justificativa'][0]);
        }

        $this->assertSame(1, Orientacao::count());
        $this->assertTrue($antiga->fresh()->ativo);
    }

    public function test_troca_para_o_mesmo_orientador_e_recusada(): void
    {
        $docente = $this->docente('A');
        $this->service->definirOrientadorInicial($this->trabalho, $docente->id, $this->secretaria);

        $this->esperaErro('orientador_vinculo_id', fn () => $this->service->trocarOrientador($this->trabalho, $docente->id, '1', 'x', $this->secretaria),
            'O novo orientador é o mesmo que o atual.');

        $this->assertSame(1, Orientacao::count());
        $this->assertSame(1, Orientacao::where('ativo', true)->count());
    }

    public function test_coorientador_promovido_a_orientador_encerra_a_coorientacao(): void
    {
        $this->service->definirOrientadorInicial($this->trabalho, $this->docente('A')->id, $this->secretaria);
        $co = $this->docente('Co');
        $coorientacao = $this->service->incluirCoorientador($this->trabalho, $co->id, $this->secretaria);

        $nova = $this->service->trocarOrientador($this->trabalho, $co->id, '1', 'Promoção', $this->secretaria);

        $this->assertFalse($coorientacao->fresh()->ativo);
        $this->assertTrue($coorientacao->fresh()->fim_em->isSameDay(today()));
        $this->assertSame('orientador', $nova->tipo);
        $this->assertSame(1, $this->trabalho->fresh()->orientacoes()->where('docente_programa_vinculo_id', $co->id)->count());
    }

    public function test_ex_orientador_pode_voltar_como_coorientador(): void
    {
        $a = $this->docente('A');
        $this->service->definirOrientadorInicial($this->trabalho, $a->id, $this->secretaria);
        $this->service->trocarOrientador($this->trabalho, $this->docente('B')->id, '1', 'Troca', $this->secretaria);

        $co = $this->service->incluirCoorientador($this->trabalho, $a->id, $this->secretaria);

        $this->assertTrue($co->ativo);
        $this->assertSame(2, Orientacao::where('docente_programa_vinculo_id', $a->id)->count());
    }

    public function test_duas_trocas_no_mesmo_dia_preservam_tudo(): void
    {
        $primeira = $this->service->definirOrientadorInicial($this->trabalho, $this->docente('A')->id, $this->secretaria);
        $segunda = $this->service->trocarOrientador($this->trabalho, $this->docente('B')->id, '1', 'Troca 1', $this->secretaria);
        $terceira = $this->service->trocarOrientador($this->trabalho, $this->docente('C')->id, '2', 'Troca 2', $this->secretaria);

        $this->assertCount(3, $this->trabalho->orientacoesHistorico()->get());
        $this->assertSame(1, Orientacao::where('ativo', true)->count());

        $segunda->refresh();
        $this->assertTrue($segunda->inicio_em->isSameDay(today()));
        $this->assertTrue($segunda->fim_em->isSameDay(today()));
        $this->assertFalse($primeira->fresh()->ativo);
        $this->assertSame($terceira->id, $this->trabalho->fresh()->orientadorVigente()?->id);
    }

    public function test_limite_de_coorientadores_conta_so_vigentes(): void
    {
        $this->programa->update(['max_coorientadores' => 1]);
        $this->service->definirOrientadorInicial($this->trabalho, $this->docente('O')->id, $this->secretaria);

        $primeira = $this->service->incluirCoorientador($this->trabalho, $this->docente('Co1')->id, $this->secretaria);
        $this->service->retirarCoorientador($primeira, $this->secretaria);
        $this->service->incluirCoorientador($this->trabalho, $this->docente('Co2')->id, $this->secretaria);

        $terceiro = $this->docente('Co3');
        $this->esperaErro('coorientador_vinculo_id', fn () => $this->service->incluirCoorientador($this->trabalho, $terceiro->id, $this->secretaria),
            'O programa permite no máximo 1 coorientador(es) por discente.');
    }

    public function test_orientador_nao_pode_ser_coorientador(): void
    {
        $o = $this->docente('O');
        $this->service->definirOrientadorInicial($this->trabalho, $o->id, $this->secretaria);

        $this->esperaErro('coorientador_vinculo_id', fn () => $this->service->incluirCoorientador($this->trabalho, $o->id, $this->secretaria),
            'O orientador não pode ser também coorientador.');
    }

    public function test_coorientador_repetido_e_recusado(): void
    {
        $this->service->definirOrientadorInicial($this->trabalho, $this->docente('O')->id, $this->secretaria);
        $co = $this->docente('Co');
        $this->service->incluirCoorientador($this->trabalho, $co->id, $this->secretaria);

        $this->esperaErro('coorientador_vinculo_id', fn () => $this->service->incluirCoorientador($this->trabalho, $co->id, $this->secretaria),
            'Este docente já é coorientador do discente.');
    }

    public function test_externo_pode_ser_coorientador_mas_nao_orientador(): void
    {
        $externo = $this->docente('Externo', 'externo', false);

        $this->esperaErro('orientador_vinculo_id', fn () => $this->service->definirOrientadorInicial($this->trabalho, $externo->id, $this->secretaria));

        $this->service->definirOrientadorInicial($this->trabalho, $this->docente('O')->id, $this->secretaria);
        $co = $this->service->incluirCoorientador($this->trabalho, $externo->id, $this->secretaria);
        $this->assertSame('coorientador', $co->tipo);

        $deOutroPrograma = $this->docente('Fora', 'interno', true, Programa::create(['nome' => 'Outro']));
        $this->esperaErro('coorientador_vinculo_id', fn () => $this->service->incluirCoorientador($this->trabalho, $deOutroPrograma->id, $this->secretaria),
            'Selecione um docente ativo do programa do discente.');
    }

    public function test_retirar_coorientador_mantem_o_registro(): void
    {
        $this->service->definirOrientadorInicial($this->trabalho, $this->docente('O')->id, $this->secretaria);
        $co = $this->service->incluirCoorientador($this->trabalho, $this->docente('Co')->id, $this->secretaria);

        $this->service->retirarCoorientador($co, $this->secretaria);

        $co->refresh();
        $this->assertFalse($co->ativo);
        $this->assertTrue($co->fim_em->isSameDay(today()));
        $this->assertTrue($this->trabalho->orientacoesHistorico()->get()->contains('id', $co->id));
        $this->assertFalse($this->trabalho->fresh()->orientacoes->contains('id', $co->id));
    }

    public function test_relacao_orientacoes_do_docente_so_traz_vigentes(): void
    {
        $a = $this->docente('A');
        $this->service->definirOrientadorInicial($this->trabalho, $a->id, $this->secretaria);
        $this->service->trocarOrientador($this->trabalho, $this->docente('B')->id, '1', 'Troca', $this->secretaria);

        $this->assertCount(0, $a->fresh()->orientacoes);
    }

    public function test_orientacao_criada_sem_data_recebe_inicio_hoje(): void
    {
        $o = Orientacao::create([
            'trabalho_academico_id' => $this->trabalho->id,
            'docente_programa_vinculo_id' => $this->docente('A')->id,
            'tipo' => 'orientador', 'ordem' => 1, 'ativo' => true,
        ]);

        $this->assertTrue($o->fresh()->inicio_em->isSameDay(today()));
    }
}
