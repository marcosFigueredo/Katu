<?php

namespace Tests\Feature;

use App\Jobs\ProcessarSubmissaoLatexJob;
use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\ProgramaTemplateLatex;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\TrabalhoSubmissaoLatex;
use App\Models\User;
use App\Services\Latex\CompiladorLatex;
use App\Services\Latex\ProjetoLatex;
use App\Services\Latex\ResultadoCompilacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SubmissaoLatexTest extends TestCase
{
    use RefreshDatabase;

    private CompiladorFalso $compilador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->compilador = new CompiladorFalso;
        $this->app->instance(CompiladorLatex::class, $this->compilador);
    }

    protected function tearDown(): void
    {
        foreach ($this->compilador->diretorios as $dir) {
            if (is_dir($dir)) {
                array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*') ?: []);
                @rmdir($dir);
            }
        }
        parent::tearDown();
    }

    public function test_programa_com_template_mostra_envio_zip_e_esconde_pdf(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee(route('meu-trabalho.submissao-latex.store'), false)
            ->assertSee('name="zip"', false)
            ->assertSee(route('programa-template.download', $trabalho->programa_id), false)
            ->assertDontSee('name="pdf"', false);
    }

    public function test_envio_zip_cria_submissao_e_despacha_job(): void
    {
        Queue::fake();
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        $resposta = $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.store'), [
            'tipo_documento' => 'qualificacao',
            'zip' => $this->zipUpload(),
        ]);

        $submissao = TrabalhoSubmissaoLatex::firstOrFail();
        $resposta->assertRedirect(route('meu-trabalho.submissao-latex.show', $submissao));
        $this->assertSame('processando', $submissao->status);
        $this->assertSame($banca->id, (int) $submissao->banca_id);
        $this->assertSame($trabalho->id, (int) $submissao->trabalho_academico_id);
        $this->assertSame($discente->id, (int) $submissao->enviado_por_id);
        $this->assertSame('qualificacao', $submissao->tipo_documento);
        $this->assertSame(64, strlen($submissao->zip_hash_sha256));
        Storage::disk('local')->assertExists($submissao->zip_path);
        Queue::assertPushed(ProcessarSubmissaoLatexJob::class);
    }

    public function test_envio_zip_sem_banca_proposta_e_recusado(): void
    {
        Queue::fake();
        [$discente] = $this->estrutura();

        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.store'), [
            'tipo_documento' => 'qualificacao',
            'zip' => $this->zipUpload(),
        ])->assertSessionHasErrors(['zip' => 'Proponha a banca antes de enviar o trabalho.']);

        $this->assertDatabaseCount('trabalho_submissoes_latex', 0);
        Queue::assertNothingPushed();
    }

    public function test_job_marca_ok_e_atualiza_trabalho(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);

        $submissao = $this->enviarZip($discente);

        $this->assertSame('ok', $submissao->status, (string) $submissao->log_resumido);
        $this->assertTrue($submissao->numeracao_linhas);
        $this->assertSame('pt', $submissao->idioma);
        $this->assertSame('dissertacao.tex', $submissao->tex_principal);
        $this->assertNotNull($submissao->pdf_banca_path);
        Storage::disk('local')->assertExists($submissao->pdf_banca_path);
        $this->assertTrue($this->compilador->chamadas[0]['numerar']);

        $itens = collect($submissao->conformidade)->keyBy('codigo');
        $this->assertSame('ok', $itens['orientador']['status']);
        $this->assertSame('ok', $itens['membros_banca']['status']);
        $this->assertSame('Helena Souza Lima; Carlos Henrique Almeida', $itens['membros_banca']['valor_sistema']);

        $trabalho->refresh();
        $this->assertSame('Este é um título dentro do limite de 150 caracteres e será aceito.', $trabalho->titulo);
        $this->assertStringStartsWith('A segmentação para a caligrafia', $trabalho->resumo);
        $this->assertSame('modelagem; simulação; otimização', $trabalho->palavras_chave);

        $this->actingAs($discente)->get(route('meu-trabalho.submissao-latex.show', $submissao))
            ->assertOk()
            ->assertSee('Enviar à homologação')
            ->assertSee('Helena Souza Lima');

        $this->actingAs($discente)->getJson(route('meu-trabalho.submissao-latex.show', $submissao))
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_job_marca_nao_ok_sem_resumo(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);

        $submissao = $this->enviarZip($discente, function (string $tex) {
            return preg_replace('/\\\\begin\{resumo\}.*?\\\\end\{resumo\}/s', '', $tex);
        });

        $this->assertSame('nao_ok', $submissao->status);
        $resumo = collect($submissao->conformidade)->firstWhere('codigo', 'resumo_pt');
        $this->assertSame('ausente', $resumo['status']);
        $this->assertTrue($resumo['bloqueia']);

        $this->actingAs($discente)->get(route('meu-trabalho.submissao-latex.show', $submissao))
            ->assertOk()
            ->assertDontSee('Enviar à homologação');
    }

    public function test_job_marca_nao_ok_quando_nao_compila(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);
        $this->compilador->sucessoComNumeracao = false;
        $this->compilador->sucessoSemNumeracao = false;
        $this->compilador->log = "! Undefined control sequence.\nl.42 \\foo";

        $submissao = $this->enviarZip($discente);

        $this->assertSame('nao_ok', $submissao->status);
        $this->assertCount(2, $this->compilador->chamadas, (string) $submissao->log_resumido);
        $this->assertNull($submissao->pdf_banca_path);
        $this->assertStringContainsString('Undefined control sequence', $submissao->log_resumido);
    }

    public function test_job_limpa_diretorio_de_saida_tambem_na_falha(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);
        $this->compilador->sucessoComNumeracao = false;
        $this->compilador->sucessoSemNumeracao = false;

        $this->enviarZip($discente);

        $this->assertCount(2, $this->compilador->diretorios);
        foreach ($this->compilador->diretorios as $dir) {
            $this->assertDirectoryDoesNotExist($dir);
        }

        // e no sucesso também
        $this->compilador->sucessoComNumeracao = true;
        $this->compilador->diretorios = [];
        $this->enviarZip($discente);
        $this->assertCount(1, $this->compilador->diretorios);
        $this->assertDirectoryDoesNotExist($this->compilador->diretorios[0]);
    }

    public function test_sem_numeracao_quando_lineno_falha_mas_documento_compila(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);
        $this->compilador->sucessoComNumeracao = false;

        $submissao = $this->enviarZip($discente);

        $this->assertSame('ok', $submissao->status, (string) $submissao->log_resumido);
        $this->assertFalse($submissao->numeracao_linhas);
        $this->assertContains('Numeração de linhas indisponível com esta classe', $submissao->avisos);
        $this->assertNotNull($submissao->pdf_banca_path);
        $this->assertSame([true, false], array_column($this->compilador->chamadas, 'numerar'));
    }

    public function test_enviar_homologacao_exige_ok_e_cria_documento(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        $naoOk = $this->enviarZip($discente, fn (string $tex) => preg_replace('/\\\\begin\{resumo\}.*?\\\\end\{resumo\}/s', '', $tex));
        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.enviar-homologacao', $naoOk))
            ->assertSessionHasErrors('homologacao');
        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
        $this->assertDatabaseCount('trabalho_documentos', 0);

        $ok = $this->enviarZip($discente);
        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.enviar-homologacao', $ok))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $documento = TrabalhoDocumento::firstOrFail();
        $this->assertSame($ok->id, (int) $documento->submissao_latex_id);
        $this->assertSame('qualificacao', $documento->tipo_documento);
        $this->assertSame('concluida', $documento->status_analise);
        Storage::disk('local')->assertExists($documento->arquivo_pdf_path);
        $this->assertNotSame($ok->pdf_banca_path, $documento->arquivo_pdf_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($documento->arquivo_pdf_path)), $documento->arquivo_pdf_hash_sha256);
        $this->assertDatabaseCount('documento_analises', 0);

        $banca->refresh();
        $this->assertSame('aguardando', $banca->homologacao_status);
        $this->assertSame($documento->id, (int) $banca->documento_homologacao_id);
    }

    public function test_reenvio_torna_nova_submissao_vigente_e_antiga_nao_envia(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        $antiga = $this->enviarZip($discente);
        $nova = $this->enviarZip($discente);

        $this->assertSame($nova->id, $trabalho->fresh()->submissaoLatexVigente->id);
        $this->assertCount(2, $trabalho->fresh()->submissoesLatex);

        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.enviar-homologacao', $antiga))
            ->assertSessionHasErrors('homologacao');
        $this->assertSame('proposta', $banca->fresh()->homologacao_status);

        $this->actingAs($discente)->get(route('meu-trabalho.submissao-latex.show', $antiga))
            ->assertOk()
            ->assertDontSee('Enviar à homologação');

        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.enviar-homologacao', $nova))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_discente_nao_ve_submissao_de_outro(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);
        $submissao = $this->enviarZip($discente);

        $outro = User::factory()->discente()->create(['primeiro_acesso' => false]);

        $this->actingAs($outro)->get(route('meu-trabalho.submissao-latex.show', $submissao))->assertForbidden();
        $this->actingAs($outro)->getJson(route('meu-trabalho.submissao-latex.show', $submissao))->assertForbidden();
        $this->actingAs($outro)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']))->assertForbidden();
        $this->actingAs($outro)->post(route('meu-trabalho.submissao-latex.enviar-homologacao', $submissao))->assertForbidden();
    }

    public function test_conferencia_so_para_o_dono(): void
    {
        [$discente, $secretaria, $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $submissao = $this->enviarZip($discente);
        Storage::disk('local')->put("submissoes-latex/{$submissao->id}/conferencia.pdf", '%PDF-1.4 conferencia');
        $submissao->update(['pdf_conferencia_path' => "submissoes-latex/{$submissao->id}/conferencia.pdf"]);

        $membro = $banca->avaliadores()->with('docenteProgramaVinculo.docenteProfile.user')->first()
            ->docenteProgramaVinculo->docenteProfile->user;
        $docenteAlheio = User::factory()->docente()->create();

        // banca: dono, Secretaria do programa e membro da banca
        $this->actingAs($discente)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']))->assertOk();
        $this->actingAs($secretaria)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']))->assertOk();
        $this->actingAs($membro)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']))->assertOk();
        $this->actingAs($docenteAlheio)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']))->assertForbidden();

        // conferência: só o dono
        $this->actingAs($discente)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'conferencia']))->assertOk();
        $this->actingAs($secretaria)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'conferencia']))->assertForbidden();
        $this->actingAs($membro)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'conferencia']))->assertForbidden();

        // Secretaria de outro programa não vê
        $outraSec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $outroPrograma = Programa::create(['nome' => 'Outro', 'sigla' => 'OUT']);
        $this->vincularSecretaria($outraSec, $outroPrograma);
        $this->actingAs($outraSec)->get(route('meu-trabalho.submissao-latex.pdf', [$submissao, 'banca']))->assertForbidden();
    }

    public function test_programa_sem_template_continua_no_pdf(): void
    {
        Queue::fake();
        [$discente, , $trabalho] = $this->estrutura(comTemplate: false);
        $this->proporBanca($discente, $trabalho);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee('name="pdf"', false)
            ->assertDontSee('name="zip"', false);

        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.store'), [
            'tipo_documento' => 'qualificacao',
            'zip' => $this->zipUpload(),
        ])->assertSessionHasErrors('zip');
        $this->assertDatabaseCount('trabalho_submissoes_latex', 0);
    }

    // ─── apoio ───────────────────────────────────────────────────────────

    private function enviarZip(User $discente, ?callable $alterarTex = null): TrabalhoSubmissaoLatex
    {
        $this->actingAs($discente)->post(route('meu-trabalho.submissao-latex.store'), [
            'tipo_documento' => 'qualificacao',
            'zip' => $this->zipUpload($alterarTex),
        ])->assertSessionHasNoErrors();

        return TrabalhoSubmissaoLatex::latest('id')->firstOrFail();
    }

    /** Zip do template PPGMSB com orientador e banca iguais aos do cadastro de teste. */
    private function zipUpload(?callable $alterarTex = null): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'subm');
        copy(base_path('docs/classppgmsb.zip'), $tmp);
        $zip = new ZipArchive;
        $zip->open($tmp);
        $tex = $zip->getFromName('classppgmsb/dissertacao.tex');
        // sem coorientadores (o trabalho não tem) e só os membros A e B (os da banca proposta)
        $tex = preg_replace('/^\\\\Coorientador[A-C]\w+\{.*$/m', '', $tex);
        $tex = preg_replace('/^\\\\Membro[C-E]\w+\{.*$/m', '', $tex);
        if ($alterarTex) {
            $tex = $alterarTex($tex);
        }
        $zip->addFromString('classppgmsb/dissertacao.tex', $tex);
        $zip->close();

        $conteudo = file_get_contents($tmp);
        @unlink($tmp);

        return UploadedFile::fake()->createWithContent('projeto.zip', $conteudo);
    }

    private function proporBanca(User $discente, TrabalhoAcademico $trabalho): Banca
    {
        $avaliadores = DocenteProgramaVinculo::where('programa_id', $trabalho->programa_id)
            ->where('pode_avaliar', true)->get();

        $this->actingAs($discente)->post(route('bancas.store'), [
            'tipo' => 'qualificacao',
            'local' => 'Alagoinhas/BA',
            'quantidade_avaliadores' => 2,
            'avaliador_interno_id' => $avaliadores->firstWhere('tipo_vinculo', 'interno')->id,
            'avaliador_externo_id' => $avaliadores->firstWhere('tipo_vinculo', 'externo')->id,
        ])->assertSessionHasNoErrors();

        return Banca::where('trabalho_academico_id', $trabalho->id)->latest('id')->firstOrFail();
    }

    private function vincularSecretaria(User $secretaria, Programa $programa): void
    {
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function estrutura(bool $comTemplate = true): array
    {
        $secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);
        $this->vincularSecretaria($secretaria, $programa);

        if ($comTemplate) {
            Storage::disk('local')->put("programas/{$programa->id}/template-latex/template.zip", file_get_contents(base_path('docs/classppgmsb.zip')));
            ProgramaTemplateLatex::create([
                'programa_id' => $programa->id,
                'arquivo_path' => "programas/{$programa->id}/template-latex/template.zip",
                'arquivo_nome_original' => 'classppgmsb.zip',
                'hash_sha256' => str_repeat('a', 64),
                'tex_principal' => 'dissertacao.tex',
                'classe_documento' => 'classPPGMSB',
                'motor' => 'pdflatex',
                'mapa_elementos' => ['titulo_pt' => ['tipo' => 'comando', 'nome' => 'TituloBR']],
                'enviado_por' => $secretaria->id,
                'enviado_em' => now(),
            ]);
        }

        $discenteUser = User::factory()->discente()->create(['primeiro_acesso' => false, 'name' => 'Gildásio Moreira de Souza']);
        $profile = DiscenteProfile::create([
            'user_id' => $discenteUser->id,
            'orcid_url' => 'https://orcid.org/0000-0002-1825-0097',
            'ativo' => true,
        ]);
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

        $orientador = $this->docente($programa, 'Antonio Guimarães Rosa', 'interno', orientar: true);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $orientador->id,
            'tipo' => 'orientador',
            'ordem' => 1,
            'ativo' => true,
        ]);
        $this->docente($programa, 'Helena Souza Lima', 'interno');
        $this->docente($programa, 'Carlos Henrique Almeida', 'externo');

        return [$discenteUser, $secretaria, $trabalho->fresh()];
    }

    private function docente(Programa $programa, string $nome, string $tipo, bool $orientar = false): DocenteProgramaVinculo
    {
        $user = User::factory()->docente()->create(['name' => $nome]);
        $profile = DocenteProfile::create([
            'user_id' => $user->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => $tipo === 'interno' ? 'UNEB' : 'UFBA',
            'instituicao_sigla' => $tipo === 'interno' ? 'UNEB' : 'UFBA',
            'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo,
            'pode_orientar' => $orientar,
            'pode_avaliar' => ! $orientar,
            'ativo' => true,
        ]);
    }
}

/** Compilador falso: grava um PDF fake num diretório temporário próprio por chamada. */
class CompiladorFalso implements CompiladorLatex
{
    public bool $sucessoComNumeracao = true;

    public bool $sucessoSemNumeracao = true;

    public string $log = '';

    /** @var list<array{numerar: bool, motor: string}> */
    public array $chamadas = [];

    /** @var list<string> */
    public array $diretorios = [];

    public function compilar(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, ?string $apendiceTex = null): ResultadoCompilacao
    {
        $this->chamadas[] = ['numerar' => $numerarLinhas, 'motor' => $motor];
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexfake_'.bin2hex(random_bytes(6));
        mkdir($dir);
        $this->diretorios[] = $dir;

        $sucesso = $numerarLinhas ? $this->sucessoComNumeracao : $this->sucessoSemNumeracao;
        $base = pathinfo($projeto->texPrincipal, PATHINFO_FILENAME);
        $pdf = $dir.DIRECTORY_SEPARATOR.$base.'.pdf';
        file_put_contents($pdf, '%PDF-1.4 fake '.($numerarLinhas ? 'numerado' : 'simples'));
        $bbl = $dir.DIRECTORY_SEPARATOR.$base.'.bbl';
        file_put_contents($bbl, '\begin{thebibliography}{1}\bibitem{a} A. 2025.\end{thebibliography}');

        return new ResultadoCompilacao($sucesso, $sucesso ? $pdf : null, $bbl, $this->log ?: 'Compilação falsa.', [], $dir);
    }
}
