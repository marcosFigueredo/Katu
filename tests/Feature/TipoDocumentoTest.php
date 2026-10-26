<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\DocumentoChecklist;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use App\Services\PedidoHomologacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TipoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('tiposDeDefesa')]
    public function test_upload_de_dissertacao_ou_tese_e_salvo(string $tipo): void
    {
        Storage::fake('local');
        [$discente, $trabalho] = $this->estruturaComBancaDeDefesa();

        $this->actingAs($discente)->post(route('meu-trabalho.store'), [
            'tipo_documento' => $tipo,
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->createWithContent('trabalho.pdf', file_get_contents(base_path('docs/dissertacao.pdf'))),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TrabalhoDocumento::where('trabalho_academico_id', $trabalho->id)->where('tipo_documento', $tipo)->count());
    }

    public static function tiposDeDefesa(): array
    {
        return ['dissertacao' => ['dissertacao'], 'tese' => ['tese']];
    }

    public function test_dissertacao_e_tese_usam_o_checklist_de_defesa(): void
    {
        $this->assertSame('defesa', DocumentoChecklist::tipoDoChecklist('dissertacao'));
        $this->assertSame('defesa', DocumentoChecklist::tipoDoChecklist('tese'));
        $this->assertSame('defesa', DocumentoChecklist::tipoDoChecklist('defesa'));
        $this->assertSame('qualificacao', DocumentoChecklist::tipoDoChecklist('qualificacao'));
        $this->assertSame('versao_final', DocumentoChecklist::tipoDoChecklist('versao_final'));
    }

    public function test_pedido_aceita_dissertacao_tese_e_defesa_antiga_na_banca_de_defesa(): void
    {
        [$discente, $trabalho] = $this->estruturaComBancaDeDefesa();
        $banca = Banca::where('trabalho_academico_id', $trabalho->id)->firstOrFail();

        foreach (['dissertacao', 'tese', 'defesa'] as $tipo) {
            $doc = TrabalhoDocumento::create([
                'trabalho_academico_id' => $trabalho->id,
                'enviado_por_id' => $discente->id,
                'tipo_documento' => $tipo,
                'arquivo_pdf_path' => 'trabalhos/x.pdf',
                'arquivo_pdf_nome_original' => 'x.pdf',
                'enviado_em' => now(),
            ]);
            $this->assertTrue(PedidoHomologacaoService::documentoCompativel($banca, $doc), $tipo);
        }
    }

    private function estruturaComBancaDeDefesa(): array
    {
        [$discente, $trabalho] = $this->estrutura();
        Banca::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
            'status' => 'cadastrada',
            'homologacao_status' => 'proposta',
            'homologacao_tentativa' => 1,
        ]);

        return [$discente, $trabalho];
    }

    private function estrutura(): array
    {
        $secretaria = User::factory()->secretaria()->create(['primeiro_acesso' => false]);
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $programa = Programa::create(['nome' => 'PPGMSB', 'sigla' => 'PPGMSB', 'instituicao_id' => $instituicao->id]);
        DB::table('programa_usuarios')->insert([
            'programa_id' => $programa->id,
            'user_id' => $secretaria->id,
            'categoria_no_programa' => 'secretaria',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $discenteUser = User::factory()->discente()->create(['primeiro_acesso' => false]);
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

        $orientadorUser = User::factory()->docente()->create();
        $orientadorProfile = DocenteProfile::create([
            'user_id' => $orientadorUser->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UNEB',
            'instituicao_sigla' => 'UNEB',
            'ativo' => true,
        ]);
        $orientador = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $orientadorProfile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $trabalho->discente_programa_vinculo_id,
            'docente_programa_vinculo_id' => $orientador->id,
            'tipo' => 'orientador',
            'ordem' => 1,
            'ativo' => true,
        ]);

        foreach ([['interno', 'UNEB', 'UNEB'], ['externo', 'UFBA', 'UFBA']] as [$tipo, $sigla, $nome]) {
            $user = User::factory()->docente()->create();
            $docenteProfile = DocenteProfile::create([
                'user_id' => $user->id,
                'titulacao' => 'Doutorado',
                'instituicao_origem' => $nome,
                'instituicao_sigla' => $sigla,
                'ativo' => true,
            ]);
            DocenteProgramaVinculo::create([
                'programa_id' => $programa->id,
                'docente_profile_id' => $docenteProfile->id,
                'tipo_vinculo' => $tipo,
                'pode_orientar' => false,
                'pode_avaliar' => true,
                'ativo' => true,
            ]);
        }

        return [$discenteUser, $trabalho->fresh()];
    }
}
