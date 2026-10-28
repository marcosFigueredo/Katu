<?php

namespace Tests\Feature;

use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use App\Services\OrientacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CadastroDiscenteOrientadorTest extends TestCase
{
    use RefreshDatabase;

    private User $sec;
    private Programa $programa;
    private ProgramaCurso $curso;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $this->programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($this->sec, $this->programa);
        $this->curso = app(CursoProgramaService::class)->criar($this->programa, 'mestrado', 'academico');
    }

    private function docente(string $nome, bool $podeOrientar = true, ?Programa $programa = null): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create(['name' => $nome])->id,
            'instituicao_sigla' => 'UNEB', 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => ($programa ?? $this->programa)->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $podeOrientar ? 'interno' : 'externo', 'pode_orientar' => $podeOrientar, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function dadosDiscente(array $extra = []): array
    {
        return [
            'name' => 'Ana', 'email' => 'ana@example.com', 'categoria' => 'discente',
            'matricula' => '2026001', 'programa_curso_id' => $this->curso->id,
            'lattes_url' => 'http://lattes.cnpq.br/1234567890123456',
            ...$extra,
        ];
    }

    public function test_cadastro_de_discente_sem_orientador_e_recusado(): void
    {
        $this->actingAs($this->sec)->post(route('usuarios.store'), $this->dadosDiscente())
            ->assertSessionHasErrors(['orientador_vinculo_id' => 'Selecione o orientador do discente.']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    public function test_cadastro_de_discente_cria_orientacao_vigente_com_data_de_hoje(): void
    {
        $orientador = $this->docente('Profa. Orientadora');

        $this->actingAs($this->sec)->post(route('usuarios.store'), $this->dadosDiscente(['orientador_vinculo_id' => $orientador->id]))
            ->assertSessionHasNoErrors();

        $trabalho = TrabalhoAcademico::firstOrFail();
        $vigente = $trabalho->orientadorVigente();
        $this->assertSame($orientador->id, $vigente?->docente_programa_vinculo_id);
        $this->assertTrue($vigente->inicio_em->isSameDay(today()));
        $this->assertSame($this->sec->id, $vigente->registrado_por);
    }

    public function test_orientador_sem_pode_orientar_ou_de_outro_programa_e_recusado(): void
    {
        $semPermissao = $this->docente('Externo', false);
        $deFora = $this->docente('Fora', true, Programa::create(['nome' => 'Outro']));

        foreach ([$semPermissao, $deFora] as $docente) {
            $this->actingAs($this->sec)->post(route('usuarios.store'), $this->dadosDiscente(['orientador_vinculo_id' => $docente->id]))
                ->assertSessionHasErrors('orientador_vinculo_id');
        }

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $this->assertSame(0, Orientacao::count());
    }

    public function test_cadastro_de_docente_nao_exige_orientador(): void
    {
        $this->actingAs($this->sec)->post(route('usuarios.store'), [
            'name' => 'Prof', 'email' => 'prof@example.com', 'categoria' => 'docente',
            'orcid_url' => 'https://orcid.org/0000-0000-0000-0001',
        ])->assertSessionDoesntHaveErrors('orientador_vinculo_id');

        $this->assertDatabaseHas('users', ['email' => 'prof@example.com']);
    }

    public function test_discente_importado_aparece_com_selo_e_no_filtro_sem_orientador(): void
    {
        $cabecalho = 'nome;email;matricula;curso;data_ingresso;lattes;linha_pesquisa;telefone';
        $linha = 'Bia Importada;bia@exemplo.com;77;mestrado_academico;1/3/2024;http://lattes.cnpq.br/1234567890123456;;';
        $arquivo = UploadedFile::fake()->createWithContent('discentes.csv', $cabecalho."\n".$linha);

        $this->actingAs($this->sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $arquivo]);
        $this->actingAs($this->sec)->post(route('usuarios.importar.confirmar'), ['token' => session('importacao_discentes_token')])->assertOk();

        $orientador = $this->docente('Profa. Orientadora');
        $this->actingAs($this->sec)->post(route('usuarios.store'), $this->dadosDiscente([
            'name' => 'Caio Orientado', 'email' => 'caio@example.com', 'orientador_vinculo_id' => $orientador->id,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->sec)->get(route('usuarios.index', ['classe' => 'sem_orientador']))
            ->assertOk()
            ->assertSee('Bia Importada')
            ->assertSee('sem orientador')
            ->assertDontSee('Caio Orientado');

        $this->actingAs($this->sec)->get(route('usuarios.index', ['classe' => 'discentes']))
            ->assertSee('Bia Importada')
            ->assertSee('Caio Orientado')
            ->assertSee('sem orientador');
    }

    public function test_discente_cujo_orientador_foi_trocado_nao_aparece_sem_orientador(): void
    {
        $a = $this->docente('Profa. A');
        $this->actingAs($this->sec)->post(route('usuarios.store'), $this->dadosDiscente(['orientador_vinculo_id' => $a->id]));
        app(OrientacaoService::class)->trocarOrientador(TrabalhoAcademico::firstOrFail(), $this->docente('Prof. B')->id, '1', 'x', $this->sec);

        $this->actingAs($this->sec)->get(route('usuarios.index', ['classe' => 'sem_orientador']))
            ->assertOk()
            ->assertDontSee('ana@example.com');
    }

    public function test_formulario_de_cadastro_lista_so_docentes_que_podem_orientar(): void
    {
        $this->docente('Profa. Pode Orientar');
        $this->docente('Prof. Não Orienta', false);
        $this->docente('Prof. De Fora', true, Programa::create(['nome' => 'Outro']));

        $this->actingAs($this->sec)->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('name="orientador_vinculo_id"', false)
            ->assertSee('Profa. Pode Orientar')
            ->assertDontSee('Prof. Não Orienta')
            ->assertDontSee('Prof. De Fora');
    }
}
