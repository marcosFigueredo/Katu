<?php

namespace Tests\Feature;

use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\User;
use App\Services\ContextoSecretaria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstituicaoLogoTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge(['nome' => 'Universidade do Estado da Bahia', 'sigla' => 'UNEB'], $extra);
    }

    public function test_cadastro_de_instituicao_exige_logotipo(): void
    {
        $sec = User::factory()->secretaria()->create();

        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados())
            ->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('instituicoes', 0);
    }

    public function test_cadastro_de_instituicao_com_logotipo_armazena_em_privado(): void
    {
        Storage::fake('local');
        $sec = User::factory()->secretaria()->create();

        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]))->assertRedirect(route('programas.create'));

        $instituicao = Instituicao::firstOrFail();
        $this->assertStringStartsWith('logos/', $instituicao->logo_path);
        Storage::disk('local')->assertExists($instituicao->logo_path);
    }

    public function test_svg_e_arquivo_disfarcado_sao_recusados(): void
    {
        Storage::fake('local');
        $sec = User::factory()->secretaria()->create();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $caminho = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($caminho, '<html><script>alert(1)</script></html>');
        $disfarcado = new UploadedFile($caminho, 'logo.png', null, null, true);

        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados(['logo' => $svg]))
            ->assertSessionHasErrors('logo');
        $this->actingAs($sec)->post(route('instituicoes.store'), $this->dados(['logo' => $disfarcado]))
            ->assertSessionHasErrors('logo');
    }

    public function test_logo_e_servido_com_nosniff(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('logos/x.png', UploadedFile::fake()->image('x.png')->getContent());
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/x.png', 'ativo' => true]);

        $this->actingAs(User::factory()->docente()->create())
            ->get(route('instituicoes.logo', $instituicao))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_secretaria_edita_somente_instituicao_do_seu_programa(): void
    {
        Storage::fake('local');
        $sec = User::factory()->secretaria()->create();
        $minha = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
        $outra = Instituicao::create(['nome' => 'UFBA', 'sigla' => 'UFBA', 'logo_path' => 'logos/b.png', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPG', 'instituicao_id' => $minha->id]);
        app(ContextoSecretaria::class)->vincular($sec, $programa);

        $this->actingAs($sec)->put(route('instituicao.update'), ['nome' => 'UNEB Editada', 'sigla' => 'UNEB'])
            ->assertRedirect();

        $this->assertSame('UNEB Editada', $minha->fresh()->nome);
        $this->assertSame('UFBA', $outra->fresh()->nome);
    }

    public function test_edicao_sem_novo_logo_mantem_o_atual(): void
    {
        $sec = User::factory()->secretaria()->create();
        $inst = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
        app(ContextoSecretaria::class)->vincular($sec, Programa::create(['nome' => 'PPG', 'instituicao_id' => $inst->id]));

        $this->actingAs($sec)->put(route('instituicao.update'), ['nome' => 'UNEB', 'sigla' => 'UNEB']);

        $this->assertSame('logos/a.png', $inst->fresh()->logo_path);
    }

    public function test_discente_recebe_403(): void
    {
        $this->actingAs(User::factory()->discente()->create())
            ->get(route('instituicoes.create'))->assertForbidden();
    }

    public function test_telas_de_cadastro_e_edicao_exibem_campo_de_logo(): void
    {
        $sec = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $this->actingAs($sec)->get(route('instituicoes.create'))
            ->assertOk()->assertSee('name="logo"', false)->assertSee(route('instituicoes.store'), false);

        $inst = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'logo_path' => 'logos/a.png', 'ativo' => true]);
        app(ContextoSecretaria::class)->vincular($sec, Programa::create(['nome' => 'PPG', 'instituicao_id' => $inst->id]));
        $this->actingAs($sec)->get(route('instituicao.edit'))
            ->assertOk()->assertSee(route('instituicoes.logo', $inst), false);
    }
}
