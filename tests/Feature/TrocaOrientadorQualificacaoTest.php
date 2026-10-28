<?php

namespace Tests\Feature;

use App\Mail\BancaRevisaoTrocaOrientadorMail;
use App\Models\Banca;
use App\Models\BancaMembro;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrocaOrientadorQualificacaoTest extends TestCase
{
    use RefreshDatabase;

    private const MOTIVO = 'A troca de orientador só é possível antes da qualificação.';

    private User $secretaria;
    private Programa $programa;
    private User $discente;
    private TrabalhoAcademico $trabalho;
    private DocenteProgramaVinculo $antigo;
    private DocenteProgramaVinculo $novo;
    private OrientacaoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);

        $this->discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $this->discente->id, 'orcid_url' => 'https://orcid.org/0000-0002-1825-0097', 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);

        $this->service = app(OrientacaoService::class);
        $this->antigo = $this->docente('Profa. Antiga');
        $this->novo = $this->docente('Prof. Novo');
        $this->service->definirOrientadorInicial($this->trabalho, $this->antigo->id, $this->secretaria);
    }

    private function docente(string $nome, string $tipo = 'interno'): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create(['name' => $nome, 'primeiro_acesso' => false])->id,
            'instituicao_sigla' => $tipo === 'externo' ? 'UFBA' : 'UNEB', 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo, 'pode_orientar' => $tipo === 'interno', 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function banca(string $tipo, string $status, string $homologacao = 'aguardando', int $avaliadores = 0): Banca
    {
        $banca = Banca::create([
            'trabalho_academico_id' => $this->trabalho->id,
            'tipo' => $tipo,
            'status' => $status,
            'homologacao_status' => $homologacao,
        ]);

        foreach (range(1, $avaliadores) as $i) {
            if ($avaliadores === 0) {
                break;
            }
            BancaMembro::create([
                'banca_id' => $banca->id,
                'docente_programa_vinculo_id' => $this->docente("Avaliador $i", $i === 1 ? 'interno' : 'externo')->id,
                'papel' => 'avaliador', 'ordem' => $i, 'ativo' => true,
            ]);
        }

        return $banca;
    }

    private function trocar(): Orientacao
    {
        return $this->service->trocarOrientador($this->trabalho, $this->novo->id, '0001.2026/55', 'Afastamento.', $this->secretaria);
    }

    private function esperaBloqueio(): void
    {
        try {
            $this->trocar();
            $this->fail('Esperava a troca recusada.');
        } catch (ValidationException $e) {
            $this->assertSame(self::MOTIVO, $e->errors()['orientador_vinculo_id'][0]);
        }

        $this->assertSame($this->antigo->id, $this->trabalho->fresh()->orientadorVigente()?->docente_programa_vinculo_id);
        $this->assertSame(1, Orientacao::count());
    }

    public function test_troca_recusada_com_qualificacao_em_avaliacao(): void
    {
        $this->banca('qualificacao', 'em_avaliacao', 'homologada');

        $this->esperaBloqueio();
    }

    public function test_troca_recusada_com_qualificacao_finalizada(): void
    {
        $this->banca('qualificacao', 'finalizada', 'homologada');

        $this->esperaBloqueio();
    }

    public function test_troca_recusada_com_banca_de_defesa(): void
    {
        $this->banca('defesa', 'cadastrada', 'proposta');

        $this->esperaBloqueio();
    }

    public function test_troca_permitida_sem_banca(): void
    {
        $this->assertNull($this->service->trocaBloqueada($this->trabalho));

        $nova = $this->trocar();

        $this->assertSame($nova->id, $this->trabalho->fresh()->orientadorVigente()?->id);
    }

    public function test_troca_devolve_qualificacao_homologada_e_avisa_avaliadores(): void
    {
        $banca = $this->banca('qualificacao', 'cadastrada', 'homologada', 2);
        $banca->update(['homologacao_observacoes' => 'Observação anterior.']);

        $this->trocar();

        $banca->refresh();
        $this->assertSame('devolvida', $banca->homologacao_status);
        $this->assertStringContainsString('Orientador trocado em '.today()->format('d/m/Y'), $banca->homologacao_observacoes);
        $this->assertStringContainsString('SEI 0001.2026/55', $banca->homologacao_observacoes);
        $this->assertStringContainsString('Observação anterior.', $banca->homologacao_observacoes);
        $this->assertStringStartsWith('Orientador trocado em', $banca->homologacao_observacoes);
        Mail::assertSent(BancaRevisaoTrocaOrientadorMail::class, 2);
        Mail::assertSent(BancaRevisaoTrocaOrientadorMail::class, fn ($m) => $m->envelope()->subject === 'Banca voltou para revisão — troca de orientador');
    }

    public function test_troca_devolve_qualificacao_proposta_sem_email(): void
    {
        $banca = $this->banca('qualificacao', 'cadastrada', 'proposta', 2);

        $this->trocar();

        $this->assertSame('devolvida', $banca->fresh()->homologacao_status);
        Mail::assertNotSent(BancaRevisaoTrocaOrientadorMail::class);
    }

    public function test_banca_cancelada_nao_bloqueia_nem_e_alterada(): void
    {
        $defesa = $this->banca('defesa', 'cancelada', 'homologada');
        $qualificacao = $this->banca('qualificacao', 'cancelada', 'homologada');

        $this->trocar();

        $this->assertSame('homologada', $defesa->fresh()->homologacao_status);
        $this->assertSame('homologada', $qualificacao->fresh()->homologacao_status);
    }

    public function test_orientador_anterior_perde_acesso(): void
    {
        $banca = $this->banca('qualificacao', 'cadastrada', 'proposta', 2);
        $userAntigo = $this->antigo->docenteProfile->user;
        $userNovo = $this->novo->docenteProfile->user;

        $this->actingAs($userAntigo)->get(route('avaliacoes.banca', $banca))->assertOk();

        $this->trocar();

        $this->actingAs($userAntigo)->get(route('avaliacoes.banca', $banca))->assertForbidden();
        $this->actingAs($userNovo)->get(route('avaliacoes.banca', $banca))->assertOk();
        $this->assertFalse($this->trabalho->fresh()->orientacoes->contains('docente_programa_vinculo_id', $this->antigo->id));
    }

    public function test_tela_esconde_troca_depois_da_qualificacao(): void
    {
        $this->banca('qualificacao', 'finalizada', 'homologada');

        $this->actingAs($this->secretaria)->get(route('usuarios.edit', $this->discente))
            ->assertOk()
            ->assertSee(self::MOTIVO)
            ->assertDontSee(route('usuarios.orientacao.trocar', $this->discente), false);
    }

    public function test_discente_sem_orientador_recebe_mensagem_ao_propor_banca(): void
    {
        Orientacao::query()->update(['ativo' => false, 'fim_em' => today()]);
        $interno = $this->docente('Interno');
        $externo = $this->docente('Externo', 'externo');

        $this->actingAs($this->discente)->post(route('bancas.store'), [
            'tipo' => 'qualificacao', 'local' => 'Sala 1', 'quantidade_avaliadores' => 2,
            'avaliador_interno_id' => $interno->id, 'avaliador_externo_id' => $externo->id,
        ])->assertSessionHasErrors([
            'trabalho_academico_id' => 'Procure a Secretaria para registrar seu orientador antes de propor a banca.',
        ]);
    }
}
