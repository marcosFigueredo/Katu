<?php

namespace Tests\Feature;

use App\Mail\PrimeiroAcessoUsuarioMail;
use App\Models\AreaConcentracao;
use App\Models\LinhaPesquisa;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ImportacaoDiscentesTest extends TestCase
{
    use RefreshDatabase;

    private const CABECALHO = 'nome;email;matricula;curso;data_ingresso;lattes;linha_pesquisa;telefone';

    private function contexto(): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => 'PPG']);
        app(ContextoSecretaria::class)->vincular($sec, $programa);
        app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');
        $area = AreaConcentracao::create(['programa_id' => $programa->id, 'nome' => 'Área', 'ativo' => true]);
        LinhaPesquisa::create(['area_concentracao_id' => $area->id, 'nome' => 'Modelagem Matemática', 'ativo' => true]);

        return [$sec, $programa];
    }

    private function csv(string $conteudo, string $nome = 'discentes.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, $conteudo);
    }

    private function linha(string $email, string $matricula, string $extra = ''): string
    {
        return "Ana Souza;{$email};{$matricula};mestrado_academico;1/3/2024;http://lattes.cnpq.br/1234567890123456;{$extra};";
    }

    public function test_previa_classifica_linhas(): void
    {
        [$sec] = $this->contexto();
        User::factory()->create(['email' => 'ja@exemplo.com']);
        $conteudo = implode("\n", [
            self::CABECALHO,
            $this->linha('ok@exemplo.com', '1', 'modelagem matematica'),
            $this->linha('ja@exemplo.com', '2'),
            'Sem Lattes;x@exemplo.com;3;mestrado_academico;01/03/2024;;;',
            'Curso errado;y@exemplo.com;4;doutorado_academico;01/03/2024;http://lattes.cnpq.br/1;;',
            $this->linha('OK@exemplo.com ', '5'),
            '',
            '',
        ]);

        $resposta = $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)]);

        $resposta->assertOk()
            ->assertSee('1 válida', false)
            ->assertSee('O Lattes é obrigatório')
            ->assertSee('Curso inexistente ou inativo')
            ->assertSee('já cadastrado');
        $this->assertDatabaseMissing('users', ['email' => 'ok@exemplo.com']);
    }

    public function test_aceita_virgula_bom_e_latin1(): void
    {
        [$sec] = $this->contexto();
        $conteudo = "\xEF\xBB\xBF" . "nome,email,matricula,curso,data_ingresso,lattes\n"
            . "Jos\xE9 Ara\xFAjo,jose@exemplo.com,10,mestrado_academico,01/03/2024,http://lattes.cnpq.br/99\n";

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)])
            ->assertOk()
            ->assertSee('José Araújo')
            ->assertSee('1 válida', false);
    }

    public function test_confirmar_cria_discentes_e_enfileira_emails_uma_unica_vez(): void
    {
        Mail::fake();
        [$sec, $programa] = $this->contexto();
        $conteudo = implode("\n", [self::CABECALHO, $this->linha('a@exemplo.com', '1'), $this->linha('b@exemplo.com', '2')]);

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)]);
        $token = session('importacao_discentes_token');

        $this->actingAs($sec)->post(route('usuarios.importar.confirmar'), ['token' => $token])->assertOk()->assertSee('2 importado', false);
        $this->actingAs($sec)->post(route('usuarios.importar.confirmar'), ['token' => $token])
            ->assertRedirect(route('usuarios.importar'))->assertSessionHasErrors('arquivo');

        $this->assertSame(2, User::where('categoria', 'discente')->count());
        $this->assertDatabaseHas('discente_programa_vinculos', ['programa_id' => $programa->id, 'matricula' => '1', 'nivel' => 'mestrado']);
        $this->assertDatabaseHas('discente_profiles', ['lattes_url' => 'http://lattes.cnpq.br/1234567890123456', 'orcid_url' => null]);
        Mail::assertQueued(PrimeiroAcessoUsuarioMail::class, 2);
    }

    public function test_matricula_repetida_no_arquivo_ou_no_programa_e_erro(): void
    {
        [$sec] = $this->contexto();
        $conteudo = implode("\n", [self::CABECALHO, $this->linha('a@exemplo.com', '7'), $this->linha('b@exemplo.com', '7')]);

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)])
            ->assertSee('Matrícula repetida');
    }

    public function test_arquivo_sem_coluna_obrigatoria_e_recusado(): void
    {
        [$sec] = $this->contexto();

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), [
            'arquivo' => $this->csv("nome;email\nAna;a@exemplo.com\n"),
        ])->assertRedirect(route('usuarios.importar'))->assertSessionHasErrors('arquivo');
    }

    public function test_token_de_outra_secretaria_e_recusado(): void
    {
        [$sec] = $this->contexto();
        $conteudo = implode("\n", [self::CABECALHO, $this->linha('a@exemplo.com', '1')]);
        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)]);
        $token = session('importacao_discentes_token');

        $outra = User::factory()->secretaria()->create();
        app(ContextoSecretaria::class)->vincular($outra, Programa::create(['nome' => 'Outro']));

        $this->actingAs($outra)->post(route('usuarios.importar.confirmar'), ['token' => $token])
            ->assertSessionHasErrors('arquivo');
        $this->assertDatabaseMissing('users', ['email' => 'a@exemplo.com']);
    }

    public function test_discente_recebe_403(): void
    {
        $this->actingAs(User::factory()->discente()->create())
            ->get(route('usuarios.importar'))->assertForbidden();
    }

    public function test_curso_inativo_nao_e_aceito(): void
    {
        [$sec, $programa] = $this->contexto();
        $programa->cursos()->update(['ativo' => false]);
        $conteudo = implode("
", [self::CABECALHO, $this->linha('a@exemplo.com', '1')]);

        $this->actingAs($sec)->post(route('usuarios.importar.analisar'), ['arquivo' => $this->csv($conteudo)])
            ->assertSee('Curso inexistente ou inativo')
            ->assertSee('0 válida', false);
    }

    public function test_tela_de_importacao_explica_formato_e_lista_cursos(): void
    {
        [$sec] = $this->contexto();

        $this->actingAs($sec)->get(route('usuarios.importar'))
            ->assertOk()
            ->assertSee('mestrado_academico')
            ->assertSee('data_ingresso')
            ->assertSee('O ORCID não é importado');
        $this->actingAs($sec)->get(route('usuarios.index'))
            ->assertSee(route('usuarios.importar'), false);
    }
}
