<?php

namespace Tests\Feature;

use App\Jobs\SugerirMapaTemplateJob;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\ProgramaTemplateLatex;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ProgramaTemplateLatexTest extends TestCase
{
    use RefreshDatabase;

    private function secretariaComPrograma(string $nome = 'PPG'): array
    {
        $sec = User::factory()->secretaria()->create();
        $programa = Programa::create(['nome' => $nome]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        return [$sec, $programa];
    }

    private function zipValido(string $nome = 'template.zip'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, file_get_contents(base_path('docs/classppgmsb.zip')));
    }

    private function discenteDoPrograma(Programa $programa, bool $ativo = true): User
    {
        $discente = User::factory()->discente()->create(['primeiro_acesso' => false]);
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'ativo' => true]);
        DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => $ativo,
        ]);

        return $discente;
    }

    public function test_secretaria_envia_template(): void
    {
        Storage::fake('local');
        [$sec, $programa] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipValido('modelo.zip')])
            ->assertRedirect(route('programa-template.index'));

        $t = ProgramaTemplateLatex::firstOrFail();
        $this->assertSame('dissertacao.tex', $t->tex_principal);
        $this->assertSame('classPPGMSB', $t->classe_documento);
        $this->assertSame($programa->id, $t->programa_id);
        Storage::disk('local')->assertExists($t->arquivo_path);
        $this->assertTrue($programa->fresh()->temTemplateLatex());

        $this->actingAs($sec)->get(route('programa-template.index'))
            ->assertOk()->assertSee('modelo.zip')->assertSee('dissertacao.tex')
            ->assertSee('Template LaTeX do programa');
    }

    public function test_reenvio_substitui_template(): void
    {
        Storage::fake('local');
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipValido()]);
        $hash1 = ProgramaTemplateLatex::firstOrFail()->hash_sha256;

        $tmp = tempnam(sys_get_temp_dir(), 'z');
        copy(base_path('docs/classppgmsb.zip'), $tmp);
        $z = new ZipArchive;
        $z->open($tmp);
        $z->addFromString('classppgmsb/extra.txt', 'x');
        $z->close();
        $novo = UploadedFile::fake()->createWithContent('v2.zip', file_get_contents($tmp));
        @unlink($tmp);

        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $novo]);

        $this->assertSame(1, ProgramaTemplateLatex::count());
        $t = ProgramaTemplateLatex::firstOrFail();
        $this->assertSame('v2.zip', $t->arquivo_nome_original);
        $this->assertNotSame($hash1, $t->hash_sha256);
    }

    public function test_zip_invalido_mostra_erro(): void
    {
        Storage::fake('local');
        [$sec] = $this->secretariaComPrograma();
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        $z = new ZipArchive;
        $z->open($tmp, ZipArchive::OVERWRITE);
        $z->addFromString('leia-me.txt', 'nada');
        $z->close();
        $arq = UploadedFile::fake()->createWithContent('ruim.zip', file_get_contents($tmp));
        @unlink($tmp);

        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $arq])
            ->assertSessionHasErrors('arquivo');
        $this->assertSame(0, ProgramaTemplateLatex::count());
    }

    public function test_arquivo_nao_zip_e_recusado(): void
    {
        Storage::fake('local');
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-template.store'), [
            'arquivo' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('arquivo');
        $this->assertSame(0, ProgramaTemplateLatex::count());
    }

    public function test_tela_nao_mostra_normas(): void
    {
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->get(route('programa-template.index'))
            ->assertOk()->assertDontSee('Normas e Procedimentos');
    }

    public function test_menu_da_secretaria_aponta_para_template(): void
    {
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->get(route('dashboard'))
            ->assertSee(route('programa-template.index'))
            ->assertDontSee('Base de Conhecimento');
    }

    public function test_docente_nao_acessa(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('programa-template.index'))->assertStatus(403);
        $this->actingAs($docente)->post(route('programa-template.store'), [])->assertStatus(403);
    }

    public function test_secretaria_de_outro_programa_nao_ve_template(): void
    {
        Storage::fake('local');
        [$secA] = $this->secretariaComPrograma('A');
        [$secB] = $this->secretariaComPrograma('B');
        $this->actingAs($secA)->post(route('programa-template.store'), ['arquivo' => $this->zipValido('do-a.zip')]);

        $this->actingAs($secB)->get(route('programa-template.index'))
            ->assertOk()->assertDontSee('do-a.zip');
        $programaA = app(ContextoSecretaria::class)->programa($secA);
        $this->actingAs($secB)->get(route('programa-template.download', $programaA))->assertStatus(403);
        $this->actingAs($secA)->get(route('programa-template.download', $programaA))->assertOk();
    }

    public function test_discente_do_programa_baixa_template(): void
    {
        Storage::fake('local');
        [$sec, $programa] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipValido()]);
        $discente = $this->discenteDoPrograma($programa);

        $this->actingAs($discente)->get(route('programa-template.download', $programa))->assertOk();
    }

    public function test_discente_de_outro_programa_nao_baixa(): void
    {
        Storage::fake('local');
        [$sec, $programaA] = $this->secretariaComPrograma('A');
        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipValido()]);
        $outro = Programa::create(['nome' => 'B']);
        $discente = $this->discenteDoPrograma($outro);

        $this->actingAs($discente)->get(route('programa-template.download', $programaA))->assertStatus(403);
    }

    public function test_diretorio_temporario_e_removido(): void
    {
        Storage::fake('local');
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipValido()]);

        $tmp = storage_path('app/private/tmp');
        $this->assertSame([], is_dir($tmp) ? array_values(array_diff(scandir($tmp), ['.', '..'])) : []);
    }

    private function zipArticle(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        $z = new ZipArchive;
        $z->open($tmp, ZipArchive::OVERWRITE);
        $z->addFromString('modelo/main.tex', file_get_contents(base_path('tests/fixtures/latex/article/main.tex')));
        $z->close();
        $arq = UploadedFile::fake()->createWithContent('article.zip', file_get_contents($tmp));
        @unlink($tmp);

        return $arq;
    }

    public function test_upload_grava_mapa_e_motor(): void
    {
        Storage::fake('local');
        Http::fake();
        Queue::fake();
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipValido()])
            ->assertRedirect(route('programa-template.index'));

        $t = ProgramaTemplateLatex::firstOrFail();
        $this->assertSame('resumo', $t->mapa_elementos['resumo_pt']['nome']);
        $this->assertSame(['tipo' => 'padrao_comando', 'nome' => 'Membro[A-Z]Nome'], $t->mapa_elementos['membros_banca']);
        $this->assertSame('pdflatex', $t->motor);
        Http::assertNothingSent();
        // as regras resolveram todos os códigos: nada para o Ollama
        Queue::assertNotPushed(SugerirMapaTemplateJob::class);
    }

    public function test_ollama_sugere_codigos_faltantes(): void
    {
        Storage::fake('local');
        Http::fake();
        Queue::fake();
        [$sec] = $this->secretariaComPrograma();

        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipArticle()])
            ->assertRedirect(route('programa-template.index'));

        // template, mapa por regras e motor gravados antes de qualquer chamada ao Ollama
        $t = ProgramaTemplateLatex::firstOrFail();
        Storage::disk('local')->assertExists($t->arquivo_path);
        $this->assertSame(['tipo' => 'comando', 'nome' => 'title'], $t->mapa_elementos['titulo_en']);
        $this->assertArrayNotHasKey('membros_banca', $t->mapa_elementos);
        $this->assertSame('pdflatex', $t->motor);
        Http::assertNothingSent();
        Queue::assertPushed(SugerirMapaTemplateJob::class, fn ($job) => $job->templateId === $t->id && $job->hash === $t->hash_sha256);
    }

    private function templateArticleSalvo(): ProgramaTemplateLatex
    {
        Storage::fake('local');
        Queue::fake();
        [$sec] = $this->secretariaComPrograma();
        $this->actingAs($sec)->post(route('programa-template.store'), ['arquivo' => $this->zipArticle()]);

        return ProgramaTemplateLatex::firstOrFail();
    }

    private function respostaOllama(): \Closure
    {
        return fn () => Http::response(['response' => json_encode([
            'membros_banca' => ['tipo' => 'padrao_comando', 'nome' => 'Membro[A-Z]Nome'],
            'titulo_en' => ['tipo' => 'comando', 'nome' => 'naoPedido'],
        ])]);
    }

    public function test_job_mescla_sugestoes_do_ollama(): void
    {
        $t = $this->templateArticleSalvo();
        Http::fake($this->respostaOllama());

        app()->call([new SugerirMapaTemplateJob($t->id, $t->hash_sha256), 'handle']);

        $t->refresh();
        $this->assertSame(['tipo' => 'padrao_comando', 'nome' => 'Membro[A-Z]Nome'], $t->mapa_elementos['membros_banca']);
        // sugestão para código já resolvido pelas regras é ignorada
        $this->assertSame(['tipo' => 'comando', 'nome' => 'title'], $t->mapa_elementos['titulo_en']);
        $this->assertSame(['tipo' => 'ambiente', 'nome' => 'abstract'], $t->mapa_elementos['abstract_en']);
        Http::assertSent(fn ($r) => str_contains($r['prompt'], 'membros_banca')
            && ! str_contains($r['prompt'], '- titulo_en:'));
        $this->assertSame([], array_values(array_diff(scandir(storage_path('app/private/tmp')), ['.', '..'])));
    }

    public function test_job_com_ollama_falhando_mantem_mapa(): void
    {
        $t = $this->templateArticleSalvo();
        $antes = $t->mapa_elementos;
        Http::fake(fn () => throw new ConnectionException('fora do ar'));

        app()->call([new SugerirMapaTemplateJob($t->id, $t->hash_sha256), 'handle']);

        $this->assertSame($antes, $t->fresh()->mapa_elementos);
    }

    public function test_job_ignora_template_substituido(): void
    {
        $t = $this->templateArticleSalvo();
        $antes = $t->mapa_elementos;
        Http::fake($this->respostaOllama());

        app()->call([new SugerirMapaTemplateJob($t->id, 'hash-de-outro-envio'), 'handle']);

        $this->assertSame($antes, $t->fresh()->mapa_elementos);
        Http::assertNothingSent();
    }
}
