<?php

namespace Tests\Feature;

use App\Models\Banca;
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
use Tests\TestCase;

class TrabalhoProjetoTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;
    private Programa $programa;
    private DocenteProgramaVinculo $orientador;
    private User $discente;
    private TrabalhoAcademico $trabalho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id]);
        app(ContextoSecretaria::class)->vincular($this->secretaria, $this->programa);

        $profile = DocenteProfile::create(['user_id' => User::factory()->docente()->create(['name' => 'Profa. Orientadora'])->id, 'instituicao_sigla' => 'UNEB', 'ativo' => true]);
        $this->orientador = DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id, 'tipo_vinculo' => 'interno',
            'categoria_docente' => 'permanente', 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);

        $this->discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $discenteProfile = DiscenteProfile::create(['user_id' => $this->discente->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $discenteProfile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function projeto(string $titulo = 'Projeto Arboviroses'): void
    {
        app(ProjetoPesquisaService::class)->cadastrar($this->orientador, [
            'titulo' => $titulo, 'descricao' => "Objetivos de {$titulo}", 'inicio_em' => '2025-01-01',
        ], $this->orientador->docenteProfile->user);
    }

    private function vincular(): void
    {
        app(OrientacaoService::class)->definirOrientadorInicial($this->trabalho, $this->orientador->id, $this->secretaria);
    }

    private function informar(string $objetivo)
    {
        return $this->actingAs($this->discente)->put(route('meu-trabalho.projeto.update'), ['objetivo' => $objetivo]);
    }

    private function banca(string $tipo, string $homologacao, string $status): Banca
    {
        return Banca::create([
            'trabalho_academico_id' => $this->trabalho->id, 'tipo' => $tipo, 'status' => $status,
            'homologacao_status' => $homologacao, 'data_hora' => now()->addMonth(),
        ]);
    }

    public function test_estudante_ve_projeto_do_orientador_e_informa_objetivo(): void
    {
        $this->projeto();
        $this->vincular();

        $this->actingAs($this->discente)->get(route('meu-trabalho.projeto.edit'))
            ->assertOk()->assertSee('Projeto Arboviroses')->assertSee('Profa. Orientadora');
        $this->informar('Avança o objetivo 2: estimar R0.')->assertSessionHasNoErrors();

        $vigente = $this->trabalho->projetoVinculoVigente();
        $this->assertSame('Avança o objetivo 2: estimar R0.', $vigente->objetivo);
        $this->assertNotNull($vigente->objetivo_informado_em);
    }

    public function test_mantem_objetivo_anterior_apos_novo_projeto(): void
    {
        $this->projeto('Projeto A');
        $this->vincular();
        $this->informar('Objetivo antigo');
        $this->projeto('Projeto B');

        $this->actingAs($this->discente)->get(route('meu-trabalho.projeto.edit'))
            ->assertSee('Objetivo antigo')->assertSee('Mantenho estes objetivos');
        $this->actingAs($this->discente)->put(route('meu-trabalho.projeto.update'), ['mantem_objetivo' => '1'])
            ->assertSessionHasNoErrors();

        $vigente = $this->trabalho->projetoVinculoVigente();
        $this->assertSame('Objetivo antigo', $vigente->objetivo);
        $this->assertTrue($vigente->mantem_objetivo);
    }

    public function test_nao_mantem_exige_novo_texto(): void
    {
        $this->projeto('Projeto A');
        $this->vincular();
        $this->informar('Objetivo antigo');
        $this->projeto('Projeto B');

        $this->actingAs($this->discente)->put(route('meu-trabalho.projeto.update'), ['mantem_objetivo' => '0', 'objetivo' => ''])
            ->assertSessionHasErrors('objetivo');
        $this->assertNull($this->trabalho->projetoVinculoVigente()->objetivo);

        $this->actingAs($this->discente)->put(route('meu-trabalho.projeto.update'), ['mantem_objetivo' => '0', 'objetivo' => 'Novo rumo'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($this->trabalho->projetoVinculoVigente()->mantem_objetivo);
    }

    public function test_bloqueado_com_qualificacao_enviada_e_liberado_apos_finalizada(): void
    {
        $this->projeto();
        $this->vincular();
        $this->informar('Primeiro');
        $qualificacao = $this->banca('qualificacao', 'aguardando', 'cadastrada');

        $this->informar('Mudou')->assertSessionHasErrors('objetivo');
        $this->actingAs($this->discente)->get(route('meu-trabalho.projeto.edit'))->assertSee('O objetivo só pode ser alterado');
        $this->assertSame('Primeiro', $this->trabalho->projetoVinculoVigente()->objetivo);

        $qualificacao->update(['homologacao_status' => 'homologada', 'status' => 'finalizada']);
        $this->informar('Novo rumo após a qualificação')->assertSessionHasNoErrors();
        $this->assertSame('Novo rumo após a qualificação', $this->trabalho->projetoVinculoVigente()->objetivo);
    }

    public function test_bloqueado_apos_enviar_defesa(): void
    {
        $this->projeto();
        $this->vincular();
        $this->informar('Primeiro');
        $this->banca('qualificacao', 'homologada', 'finalizada');
        $this->banca('defesa', 'aguardando', 'cadastrada');

        $this->informar('Mudou')->assertSessionHasErrors('objetivo');
    }

    public function test_vinculo_novo_sem_objetivo_responde_mesmo_com_defesa_enviada(): void
    {
        $this->projeto('Projeto A');
        $this->vincular();
        $this->informar('Primeiro');
        $this->banca('defesa', 'aguardando', 'cadastrada');
        $this->projeto('Projeto B');

        $this->actingAs($this->discente)->put(route('meu-trabalho.projeto.update'), ['mantem_objetivo' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Primeiro', $this->trabalho->projetoVinculoVigente()->objetivo);
    }

    public function test_mantem_sem_objetivo_anterior_e_erro_de_validacao(): void
    {
        $this->projeto();
        $this->vincular();

        $this->actingAs($this->discente)->put(route('meu-trabalho.projeto.update'), ['mantem_objetivo' => '1'])
            ->assertSessionHasErrors('objetivo');
        $this->assertNull($this->trabalho->projetoVinculoVigente()->objetivo);
    }

    public function test_sem_vinculo_mostra_aviso(): void
    {
        $this->vincular();

        $this->actingAs($this->discente)->get(route('meu-trabalho.projeto.edit'))
            ->assertOk()->assertSee('Seu orientador ainda não tem projeto de pesquisa ativo cadastrado.');
        $this->informar('Qualquer')->assertSessionHasErrors('objetivo');
    }

    public function test_outro_usuario_nao_acessa(): void
    {
        $this->actingAs($this->secretaria)->get(route('meu-trabalho.projeto.edit'))->assertForbidden();
        $this->actingAs($this->orientador->docenteProfile->user)->put(route('meu-trabalho.projeto.update'), ['objetivo' => 'x'])->assertForbidden();
    }
}
