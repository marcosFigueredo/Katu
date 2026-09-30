<?php

namespace Tests\Feature;

use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\CursoProgramaService;
use App\Services\VerificacaoBancaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VerificacaoBancaTest extends TestCase
{
    use RefreshDatabase;

    private function trabalho(?string $orcid, bool $comCurso = true, bool $comInstituicao = true): TrabalhoAcademico
    {
        $inst = $comInstituicao ? Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]) : null;
        $programa = Programa::create(['nome' => 'PPG', 'instituicao_id' => $inst?->id]);
        $curso = $comCurso ? app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico') : null;
        $profile = DiscenteProfile::create([
            'user_id' => User::factory()->discente()->create()->id, 'orcid_url' => $orcid, 'ativo' => true,
        ]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'discente_profile_id' => $profile->id,
            'programa_curso_id' => $curso?->id, 'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);

        return TrabalhoAcademico::create([
            'programa_id' => $programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento',
        ]);
    }

    private function avaliador(Programa $programa, string $tipo, string $sigla): int
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create()->id, 'instituicao_sigla' => $sigla, 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => $tipo, 'pode_avaliar' => true, 'ativo' => true,
        ])->id;
    }

    public function test_banca_conforme_passa(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097');
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', 'UFBA'),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertTrue($itens->every(fn ($i) => $i['ok']));
    }

    public function test_sem_orcid_falha(): void
    {
        $trabalho = $this->trabalho(null);
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', 'UFBA'),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertFalse($itens->firstWhere('chave', 'orcid')['ok']);
        $this->expectException(ValidationException::class);
        app(VerificacaoBancaService::class)->exigir($itens, 'avaliadores');
    }

    public function test_externo_a_instituicao_usa_sigla_da_instituicao_do_programa(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097');
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', ' uneb '),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertFalse($itens->firstWhere('chave', 'externos_instituicao')['ok']);
    }

    public function test_regra_editada_do_curso_prevalece(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097');
        $trabalho->discenteVinculo->programaCurso->regrasBanca()->where('etapa', 'qualificacao')
            ->update(['min_avaliadores' => 3, 'max_avaliadores' => 3]);
        $ids = collect([
            $this->avaliador($trabalho->programa, 'interno', 'UNEB'),
            $this->avaliador($trabalho->programa, 'externo', 'UFBA'),
        ]);

        $itens = app(VerificacaoBancaService::class)->verificar($trabalho, 'qualificacao', $ids);

        $this->assertFalse($itens->firstWhere('chave', 'quantidade')['ok']);
    }

    public function test_programa_legado_sem_curso_e_sem_instituicao_usa_padrao(): void
    {
        $trabalho = $this->trabalho('https://orcid.org/0000-0002-1825-0097', comCurso: false, comInstituicao: false);

        $regra = app(VerificacaoBancaService::class)->regraPara($trabalho, 'defesa');

        $this->assertSame(['min_avaliadores' => 2, 'max_avaliadores' => 5, 'min_internos' => 1, 'min_externos_instituicao' => 1], $regra);
        $this->assertTrue(app(VerificacaoBancaService::class)->externoInstituicao(
            DocenteProgramaVinculo::find($this->avaliador($trabalho->programa, 'externo', 'UFBA'))->load('docenteProfile'),
            null,
        ));
    }
}
