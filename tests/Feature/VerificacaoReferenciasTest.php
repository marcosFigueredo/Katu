<?php

namespace Tests\Feature;

use App\Jobs\VerificarReferenciasSubmissaoJob;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\OrcidWork;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoSubmissaoLatex;
use App\Models\User;
use App\Services\Latex\CompiladorLatex;
use App\Services\Latex\ProjetoLatex;
use App\Services\Latex\ResultadoCompilacao;
use App\Services\Latex\VerificacaoReferenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificacaoReferenciasTest extends TestCase
{
    use RefreshDatabase;

    private TrabalhoAcademico $trabalho;

    private DocenteProfile $orientador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->montarTrabalho();
    }

    public function test_doi_localizado_no_crossref(): void
    {
        Http::fake([
            'api.crossref.org/works/10.1000/abc' => Http::response(['message' => [
                'DOI' => '10.1000/abc',
                'title' => ['Modelagem em saúde'],
                'issued' => ['date-parts' => [[2021, 3]]],
                'author' => [['given' => 'Maria', 'family' => 'Silva']],
            ]]),
        ]);

        $s = $this->submissao($this->bibitem('a', 'Silva, M. (2021).', 'Modelagem em saúde', '10.1000/abc'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $ref = $s->referencias()->firstOrFail();
        $this->assertSame('localizada', $ref->status);
        $this->assertSame('crossref', $ref->fonte);
        $this->assertSame('10.1000/abc', $ref->doi_encontrado);
        $this->assertSame(2021, $ref->ano_encontrado);
        $this->assertFalse($ref->divergencia_doi);
        $this->assertFalse($ref->divergencia_ano);
        $this->assertSame('concluida', $s->fresh()->refs_status);
        $this->assertSame(1, $s->fresh()->refs_total);
    }

    public function test_doi_cai_para_openalex_quando_crossref_nao_tem(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response([], 404),
            'api.openalex.org/works/*' => Http::response([
                'doi' => 'https://doi.org/10.1000/abc',
                'display_name' => 'Modelagem em saúde',
                'publication_year' => 2021,
                'authorships' => [],
            ]),
        ]);

        $s = $this->submissao($this->bibitem('a', 'Silva, M. (2021).', 'Modelagem em saúde', '10.1000/abc'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $ref = $s->referencias()->firstOrFail();
        $this->assertSame('localizada', $ref->status);
        $this->assertSame('openalex', $ref->fonte);
    }

    public function test_sem_doi_localizado_por_titulo_no_openalex(): void
    {
        Http::fake([
            'api.openalex.org/works*' => Http::response(['results' => [
                ['display_name' => 'Outro assunto totalmente diferente', 'publication_year' => 2010, 'doi' => null, 'authorships' => []],
                ['display_name' => 'Redes bayesianas na vigilância em saúde', 'publication_year' => 2024, 'doi' => 'https://doi.org/10.5555/rd.2024.9', 'authorships' => []],
            ]]),
        ]);

        $s = $this->submissao($this->bibitem('b', 'Santos, M. (2024).', 'Redes bayesianas na vigilância em saúde'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $ref = $s->referencias()->firstOrFail();
        $this->assertSame('localizada', $ref->status);
        $this->assertSame('openalex', $ref->fonte);
        $this->assertSame('10.5555/rd.2024.9', $ref->doi_encontrado);
        $this->assertSame(2024, $ref->ano_encontrado);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openalex.org/works') && str_contains(urldecode($r->url()), 'search=Redes bayesianas'));
    }

    public function test_titulo_parecido_demais_nao_e_aceito(): void
    {
        Http::fake([
            'api.openalex.org/works*' => Http::response(['results' => [
                ['display_name' => 'Redes neurais na saúde', 'publication_year' => 2024, 'doi' => null, 'authorships' => []],
            ]]),
        ]);

        $s = $this->submissao($this->bibitem('b', 'Santos, M. (2024).', 'Redes bayesianas na vigilância em saúde'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame('nao_localizada', $s->referencias()->firstOrFail()->status);
    }

    public function test_nao_localizada_quando_nenhuma_base_encontra(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response([], 404),
            'api.openalex.org/works/*' => Http::response([], 404),
            'api.openalex.org/works?*' => Http::response(['results' => []]),
        ]);

        $s = $this->submissao(
            $this->bibitem('a', 'A. (2021).', 'Um trabalho com doi falso', '10.9999/falso')
            .$this->bibitem('b', 'B. (2022).', 'Um trabalho sem doi e sem rastro')
        );
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame(['nao_localizada', 'nao_localizada'], $s->referencias()->orderBy('id')->pluck('status')->all());
        $this->assertSame('concluida', $s->fresh()->refs_status);
    }

    public function test_nao_verificada_quando_rede_falha(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $s = $this->submissao(
            $this->bibitem('a', 'A. (2021).', 'Com doi', '10.1000/abc')
            .$this->bibitem('b', 'B. (2022).', 'Sem doi nenhum')
        );
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame(['nao_verificada', 'nao_verificada'], $s->referencias()->orderBy('id')->pluck('status')->all());
        $this->assertSame('concluida', $s->fresh()->refs_status);
    }

    public function test_falha_de_rede_nao_vai_para_o_cache(): void
    {
        $fora = true;
        Http::fake(function () use (&$fora) {
            if ($fora) {
                throw new ConnectionException('timeout');
            }

            return Http::response(['message' => ['DOI' => '10.1000/abc', 'title' => ['Com doi'], 'issued' => ['date-parts' => [[2021]]]]]);
        });
        $s = $this->submissao($this->bibitem('a', 'A. (2021).', 'Com doi', '10.1000/abc'));
        app(VerificacaoReferenciasService::class)->verificar($s);
        $this->assertSame('nao_verificada', $s->referencias()->firstOrFail()->status);

        $fora = false;
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame('localizada', $s->referencias()->firstOrFail()->status);
    }

    public function test_marca_producao_do_orientador_por_doi_em_orcid_works(): void
    {
        OrcidWork::create([
            'docente_profile_id' => $this->orientador->id,
            'orcid_id' => '0000-0001-2345-6789',
            'titulo' => 'Obra do orientador',
            'ano' => 2022,
            'doi' => 'https://doi.org/10.1000/ABC',
            'consultado_em' => now(),
        ]);
        Http::fake([
            'api.crossref.org/*' => Http::response(['message' => [
                'DOI' => '10.1000/abc', 'title' => ['Obra do orientador'], 'issued' => ['date-parts' => [[2022]]],
            ]]),
        ]);

        $s = $this->submissao($this->bibitem('a', 'R. (2022).', 'Obra do orientador', '10.1000/abc'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertTrue($s->referencias()->firstOrFail()->do_orientador);
        $this->assertSame(1, $s->fresh()->refs_orientador);
    }

    public function test_marca_producao_do_orientador_por_autor_do_openalex(): void
    {
        Http::fake([
            'api.openalex.org/works*' => Http::response(['results' => [[
                'display_name' => 'Redes bayesianas na vigilância em saúde',
                'publication_year' => 2024,
                'doi' => null,
                'authorships' => [
                    ['author' => ['display_name' => 'Fulano de Tal', 'orcid' => null]],
                    ['author' => ['display_name' => 'Rosa, Antonio Guimarães', 'orcid' => null]],
                ],
            ]]]),
        ]);

        $s = $this->submissao($this->bibitem('b', 'Santos, M. (2024).', 'Redes bayesianas na vigilância em saúde'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertTrue($s->referencias()->firstOrFail()->do_orientador);
    }

    public function test_marca_producao_do_orientador_por_orcid_do_autor(): void
    {
        Http::fake([
            'api.openalex.org/works*' => Http::response(['results' => [[
                'display_name' => 'Redes bayesianas na vigilância em saúde',
                'publication_year' => 2024,
                'doi' => null,
                'authorships' => [
                    ['author' => ['display_name' => 'A. G. Rosa', 'orcid' => 'https://orcid.org/0000-0001-2345-6789']],
                ],
            ]]]),
        ]);

        $s = $this->submissao($this->bibitem('b', 'Santos, M. (2024).', 'Redes bayesianas na vigilância em saúde'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertTrue($s->referencias()->firstOrFail()->do_orientador);
    }

    public function test_atualidade(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $s = $this->submissao(
            $this->bibitem('a', 'A. (2026).', 'Trabalho um')
            .$this->bibitem('b', 'B. (2025).', 'Trabalho dois')
            .$this->bibitem('c', 'C. (2025).', 'Trabalho tres')
            .$this->bibitem('d', 'D. (2020).', 'Trabalho quatro')
            .$this->bibitem('e', 'E, sem data.', 'Trabalho cinco'),
            2026
        );
        app(VerificacaoReferenciasService::class)->verificar($s);
        $s->refresh();

        $this->assertSame(5, $s->refs_total);
        $this->assertSame(4, $s->refs_com_ano);
        $this->assertSame('75.00', $s->refs_recentes_pct);
        $this->assertSame(75.0, (float) $s->refs_recentes_pct);
        $this->assertTrue($s->refs_atualizadas);
    }

    public function test_atualidade_igual_a_70_nao_e_atualizada(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $bbl = '';
        foreach ([2026, 2025, 2025, 2026, 2025, 2025, 2026, 2010, 2010, 2010] as $i => $ano) {
            $bbl .= $this->bibitem("r{$i}", "X. ({$ano}).", "Titulo numero {$i}");
        }
        $s = $this->submissao($bbl, 2026);
        app(VerificacaoReferenciasService::class)->verificar($s);
        $s->refresh();

        $this->assertSame(10, $s->refs_com_ano);
        $this->assertSame(70.0, (float) $s->refs_recentes_pct);
        $this->assertFalse($s->refs_atualizadas);
    }

    public function test_ano_encontrado_vale_mais_que_o_do_bbl(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response(['message' => [
                'DOI' => '10.1000/abc', 'title' => ['T'], 'issued' => ['date-parts' => [[2026]]],
            ]]),
        ]);

        $s = $this->submissao($this->bibitem('a', 'A. (2010).', 'T', '10.1000/abc'), 2026);
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame('100.00', $s->fresh()->refs_recentes_pct);
    }

    public function test_divergencia_de_ano_registrada(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response(['message' => [
                'DOI' => '10.1000/outro', 'title' => ['Modelagem em saúde'], 'issued' => ['date-parts' => [[2019]]],
            ]]),
        ]);

        $s = $this->submissao($this->bibitem('a', 'Silva, M. (2021).', 'Modelagem em saúde', '10.1000/abc'));
        app(VerificacaoReferenciasService::class)->verificar($s);

        $ref = $s->referencias()->firstOrFail();
        $this->assertTrue($ref->divergencia_ano);
        $this->assertTrue($ref->divergencia_doi);
        $this->assertSame(2021, $ref->ano_tex);
        $this->assertSame(2019, $ref->ano_encontrado);
    }

    public function test_sem_titulo_legivel_pergunta_ao_ollama(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response(['response' => json_encode(['titulo' => 'Redes bayesianas na vigilância em saúde', 'ano' => 2024])]),
            'api.openalex.org/works*' => Http::response(['results' => [[
                'display_name' => 'Redes bayesianas na vigilância em saúde', 'publication_year' => 2024, 'doi' => null, 'authorships' => [],
            ]]]),
        ]);

        $s = $this->submissao("\\bibitem{x}\nSantos M. Redes bayesianas na vigilancia em saude. Rev Dados 2024.\n");
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame('localizada', $s->referencias()->firstOrFail()->status);
    }

    public function test_ollama_falhando_deixa_nao_localizada(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response('erro', 500),
        ]);

        $s = $this->submissao("\\bibitem{x}\nSantos M. Redes bayesianas. Rev Dados 2024.\n");
        app(VerificacaoReferenciasService::class)->verificar($s);

        $this->assertSame('nao_localizada', $s->referencias()->firstOrFail()->status);
    }

    public function test_trabalho_expoe_metricas_da_ultima_submissao(): void
    {
        $this->assertNull($this->trabalho->metricasReferencias());

        $antiga = $this->submissao('');
        $antiga->update(['refs_status' => 'concluida', 'refs_total' => 3, 'refs_com_ano' => 3, 'refs_recentes_pct' => 33.33, 'refs_atualizadas' => false, 'refs_orientador' => 0]);
        $recente = $this->submissao('');
        $recente->update(['refs_status' => 'concluida', 'refs_total' => 10, 'refs_com_ano' => 8, 'refs_recentes_pct' => 87.5, 'refs_atualizadas' => true, 'refs_orientador' => 2]);
        $pendente = $this->submissao('');
        $pendente->update(['refs_status' => 'processando']);

        $m = $this->trabalho->fresh()->metricasReferencias();

        $this->assertSame(10, $m['total']);
        $this->assertSame(8, $m['com_ano']);
        $this->assertSame(87.5, $m['recentes_pct']);
        $this->assertTrue($m['atualizadas']);
        $this->assertSame(2, $m['orientador']);
    }

    // ─── job e versão de conferência ─────────────────────────────────────

    public function test_job_gera_versao_de_conferencia_com_apendice(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $compilador = new CompiladorApendiceFalso;
        $this->app->instance(CompiladorLatex::class, $compilador);

        $s = $this->submissao($this->bibitem('a', 'A. (2021).', 'Um trabalho que ninguém achou', '10.9999/x'));
        $this->guardarZip($s);

        VerificarReferenciasSubmissaoJob::dispatchSync($s);
        $s->refresh();

        $this->assertSame('concluida', $s->refs_status);
        $this->assertNotNull($s->pdf_conferencia_path);
        $this->assertNotSame($s->pdf_banca_path, $s->pdf_conferencia_path);
        Storage::disk('local')->assertExists($s->pdf_conferencia_path);
        $this->assertStringContainsString('conferencia', Storage::disk('local')->get($s->pdf_conferencia_path));

        $this->assertCount(1, $compilador->chamadas);
        $apendice = $compilador->chamadas[0]['apendice'];
        $this->assertStringContainsString('Relatório de verificação das referências', $apendice);
        $this->assertStringContainsString('não localizada — verificar', $apendice);
        $this->assertStringContainsString('10.\allowbreak{}9999/', $apendice);
        $this->assertSame('pdflatex', $compilador->chamadas[0]['motor']);
        foreach ($compilador->diretorios as $dir) {
            $this->assertDirectoryDoesNotExist($dir);
        }
    }

    public function test_job_usa_versao_da_banca_quando_apendice_nao_compila(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $compilador = new CompiladorApendiceFalso;
        $compilador->sucesso = false;
        $this->app->instance(CompiladorLatex::class, $compilador);

        $s = $this->submissao($this->bibitem('a', 'A. (2021).', 'Um trabalho', '10.9999/x'));
        $this->guardarZip($s);

        VerificarReferenciasSubmissaoJob::dispatchSync($s);
        $s->refresh();

        $this->assertSame('concluida', $s->refs_status);
        $this->assertNotNull($s->pdf_conferencia_path);
        $this->assertStringContainsString('banca', Storage::disk('local')->get($s->pdf_conferencia_path));
        $this->assertTrue(collect($s->avisos)->contains(fn ($a) => str_contains($a, 'apêndice')));
        foreach ($compilador->diretorios as $dir) {
            $this->assertDirectoryDoesNotExist($dir);
        }
    }

    public function test_job_marca_erro_sem_propagar_excecao(): void
    {
        $this->app->instance(CompiladorLatex::class, new CompiladorApendiceFalso);
        Http::fake(function () {
            throw new \RuntimeException('falha inesperada');
        });

        $s = $this->submissao($this->bibitem('a', 'A. (2021).', 'Um trabalho', '10.9999/x'));
        $this->guardarZip($s);

        VerificarReferenciasSubmissaoJob::dispatchSync($s);

        $this->assertSame('erro', $s->fresh()->refs_status);
    }

    public function test_partial_mostra_totais_e_nao_localizada(): void
    {
        $s = $this->submissao('');
        $s->update(['refs_status' => 'concluida', 'refs_total' => 2, 'refs_com_ano' => 2, 'refs_recentes_pct' => 50, 'refs_atualizadas' => false, 'refs_orientador' => 1]);
        $s->referencias()->create(['chave' => 'a', 'texto' => 'Texto A', 'status' => 'nao_localizada']);
        $s->referencias()->create(['chave' => 'b', 'texto' => 'Texto B', 'status' => 'localizada', 'do_orientador' => true]);

        $html = view('partials.relatorio-referencias', ['submissao' => $s->fresh()])->render();

        $this->assertStringContainsString('não localizada — verificar', $html);
        $this->assertStringContainsString('50', $html);
        $this->assertStringContainsString('Produção do orientador', $html);
    }

    // ─── apoio ───────────────────────────────────────────────────────────

    private function bibitem(string $chave, string $autoria, string $titulo, ?string $doi = null): string
    {
        return "\\bibitem{{$chave}}\n{$autoria}\n\\newblock {$titulo}.\n\\newblock Revista X, 1:1--2."
            .($doi ? "\n\\newblock doi:{$doi}" : '')."\n\n";
    }

    private function submissao(string $bblItens, int $anoEnvio = 2026): TrabalhoSubmissaoLatex
    {
        $s = TrabalhoSubmissaoLatex::create([
            'trabalho_academico_id' => $this->trabalho->id,
            'tipo_documento' => 'qualificacao',
            'zip_path' => 'x/projeto.zip',
            'zip_hash_sha256' => str_repeat('a', 64),
            'tex_principal' => 'dissertacao.tex',
            'motor' => 'pdflatex',
            'status' => 'ok',
            'numeracao_linhas' => true,
            'refs_status' => 'pendente',
        ]);
        $s->forceFill(['created_at' => "{$anoEnvio}-05-10 10:00:00"])->saveQuietly();

        $caminho = "{$s->diretorioArquivos()}/referencias.bbl";
        Storage::disk('local')->put($caminho, "\\begin{thebibliography}{9}\n\n{$bblItens}\\end{thebibliography}\n");
        Storage::disk('local')->put("{$s->diretorioArquivos()}/banca.pdf", '%PDF-1.4 banca');
        $s->update(['bbl_path' => $caminho, 'pdf_banca_path' => "{$s->diretorioArquivos()}/banca.pdf"]);

        return $s->fresh();
    }

    private function guardarZip(TrabalhoSubmissaoLatex $s): void
    {
        $caminho = "{$s->diretorioArquivos()}/projeto.zip";
        Storage::disk('local')->put($caminho, file_get_contents(base_path('docs/classppgmsb.zip')));
        $s->update(['zip_path' => $caminho]);
    }

    private function montarTrabalho(): void
    {
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);

        $discenteUser = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discenteUser->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado',
            'status' => 'ativo',
            'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $programa->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);

        $user = User::factory()->docente()->create(['name' => 'Antonio Guimarães Rosa']);
        $this->orientador = DocenteProfile::create([
            'user_id' => $user->id,
            'titulacao' => 'Doutorado',
            'orcid_url' => 'https://orcid.org/0000-0001-2345-6789',
            'ativo' => true,
        ]);
        $vinc = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $this->orientador->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $this->trabalho->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $vinc->id,
            'tipo' => 'orientador',
            'ordem' => 1,
            'ativo' => true,
        ]);
    }
}

/** Compilador falso para a versão de conferência: registra o apêndice recebido. */
class CompiladorApendiceFalso implements CompiladorLatex
{
    public bool $sucesso = true;

    /** @var list<array{numerar: bool, motor: string, apendice: ?string}> */
    public array $chamadas = [];

    /** @var list<string> */
    public array $diretorios = [];

    public function compilar(ProjetoLatex $projeto, string $motor, bool $numerarLinhas, ?string $apendiceTex = null): ResultadoCompilacao
    {
        $this->chamadas[] = ['numerar' => $numerarLinhas, 'motor' => $motor, 'apendice' => $apendiceTex];
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexfake_'.bin2hex(random_bytes(6));
        mkdir($dir);
        $this->diretorios[] = $dir;
        $pdf = $dir.DIRECTORY_SEPARATOR.'saida.pdf';
        file_put_contents($pdf, '%PDF-1.4 conferencia');

        return new ResultadoCompilacao($this->sucesso, $this->sucesso ? $pdf : null, null, $this->sucesso ? '' : 'erro de compilação', [], $dir);
    }
}
