<?php

namespace Tests\Feature;

use App\Models\Bolsa;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BolsaTest extends TestCase
{
    use RefreshDatabase;

    private User $sec;
    private Programa $programa;
    private ProgramaCurso $curso;
    private DocenteProgramaVinculo $orientador;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $this->programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($this->sec, $this->programa);
        $this->curso = app(CursoProgramaService::class)->criar($this->programa, 'mestrado', 'academico');

        $profile = DocenteProfile::create(['user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => 'UNEB', 'ativo' => true]);
        $this->orientador = DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => 'interno', 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function cadastrarDiscente(array $extra = []): User
    {
        $this->actingAs($this->sec)->post(route('usuarios.store'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '2026001', 'programa_curso_id' => $this->curso->id,
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
            'orientador_vinculo_id' => $this->orientador->id,
            ...$extra,
        ])->assertSessionHasNoErrors();

        return User::where('email', 'ana@example.com')->firstOrFail();
    }

    private function bolsa(array $extra = []): array
    {
        return ['agencia' => 'capes', 'modalidade' => 'mestrado', 'numero_processo' => '88887.000/2026', 'inicio_em' => '2026-03-01', 'fim_em' => '2028-02-28', ...$extra];
    }

    public function test_secretaria_registra_bolsa_ao_cadastrar_discente(): void
    {
        $this->cadastrarDiscente(['bolsa_agencia' => 'fapesb', 'bolsa_modalidade' => 'mestrado', 'bolsa_inicio_em' => '2026-03-01']);

        $bolsa = Bolsa::firstOrFail();
        $this->assertSame('fapesb', $bolsa->agencia);
        $this->assertSame($this->sec->id, $bolsa->registrado_por);
    }

    public function test_cadastro_sem_bolsa_nao_cria_bolsa(): void
    {
        $this->cadastrarDiscente();
        $this->assertSame(0, Bolsa::count());
    }

    public function test_secretaria_inclui_edita_e_remove_bolsa_na_edicao(): void
    {
        $discente = $this->cadastrarDiscente();

        $this->actingAs($this->sec)->post(route('usuarios.bolsas.store', $discente), $this->bolsa())->assertSessionHasNoErrors();
        $bolsa = Bolsa::firstOrFail();
        $this->actingAs($this->sec)->get(route('usuarios.edit', $discente))->assertSee('Bolsas')->assertSee('88887.000/2026');

        $this->actingAs($this->sec)->put(route('usuarios.bolsas.update', [$discente, $bolsa]), $this->bolsa(['agencia' => 'cnpq']))
            ->assertSessionHasNoErrors();
        $this->assertSame('cnpq', $bolsa->fresh()->agencia);

        $this->actingAs($this->sec)->delete(route('usuarios.bolsas.destroy', [$discente, $bolsa]))->assertSessionHasNoErrors();
        $this->assertSame(0, Bolsa::count());
    }

    public function test_outra_exige_nome_da_agencia(): void
    {
        $discente = $this->cadastrarDiscente();

        $this->actingAs($this->sec)->post(route('usuarios.bolsas.store', $discente), $this->bolsa(['agencia' => 'outra']))
            ->assertSessionHasErrors('agencia_outra');
        $this->actingAs($this->sec)->post(route('usuarios.bolsas.store', $discente), $this->bolsa(['agencia' => 'outra', 'agencia_outra' => 'Fundação X']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Fundação X', Bolsa::firstOrFail()->agenciaNome);
    }

    public function test_fim_antes_do_inicio_e_recusado(): void
    {
        $discente = $this->cadastrarDiscente();

        $this->actingAs($this->sec)->post(route('usuarios.bolsas.store', $discente), $this->bolsa(['fim_em' => '2025-01-01']))
            ->assertSessionHasErrors('fim_em');
    }

    public function test_discente_ve_suas_bolsas_em_meu_trabalho(): void
    {
        $discente = $this->cadastrarDiscente(['bolsa_agencia' => 'capes', 'bolsa_modalidade' => 'mestrado', 'bolsa_inicio_em' => '2026-03-01']);
        $discente->update(['primeiro_acesso' => false]);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))->assertOk()->assertSee('Bolsa')->assertSee('CAPES');
    }

    public function test_secretaria_de_outro_programa_nao_mexe(): void
    {
        $discente = $this->cadastrarDiscente();
        $this->actingAs($this->sec)->post(route('usuarios.bolsas.store', $discente), $this->bolsa());
        $bolsa = Bolsa::firstOrFail();

        $outra = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        app(ContextoSecretaria::class)->vincular($outra, Programa::create(['nome' => 'Outro']));

        $this->actingAs($outra)->post(route('usuarios.bolsas.store', $discente), $this->bolsa())->assertForbidden();
        $this->actingAs($outra)->delete(route('usuarios.bolsas.destroy', [$discente, $bolsa]))->assertForbidden();
        $this->assertSame(1, Bolsa::count());
    }

    public function test_discente_nao_acessa_rotas_da_secretaria(): void
    {
        $discente = $this->cadastrarDiscente();
        $discente->update(['primeiro_acesso' => false]);

        $this->actingAs($discente)->post(route('usuarios.bolsas.store', $discente), $this->bolsa())->assertForbidden();
    }
}
