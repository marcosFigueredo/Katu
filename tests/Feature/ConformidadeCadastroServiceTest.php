<?php

namespace Tests\Feature;

use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\Latex\ConformidadeCadastroService as S;
use App\Services\Latex\ElementosVitais as E;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConformidadeCadastroServiceTest extends TestCase
{
    use RefreshDatabase;

    private Programa $programa;
    private TrabalhoAcademico $trabalho;
    private Banca $banca;
    private S $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servico = new S();
        $instituicao = Instituicao::create(['nome' => 'UNEB', 'sigla' => 'UNEB', 'ativo' => true]);
        $this->programa = Programa::create(['nome' => 'PPG', 'sigla' => 'PPG', 'instituicao_id' => $instituicao->id, 'max_coorientadores' => 2]);

        $profile = DiscenteProfile::create(['user_id' => User::factory()->discente()->create(['name' => 'Maria Souza'])->id, 'ativo' => true]);
        $vinculo = DiscenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'discente_profile_id' => $profile->id,
            'nivel' => 'mestrado', 'status' => 'ativo', 'ativo' => true,
        ]);
        $this->trabalho = TrabalhoAcademico::create([
            'programa_id' => $this->programa->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'nivel' => 'mestrado', 'status' => 'em_andamento', 'titulo' => 'Modelagem em saúde na Bahia',
        ]);
        $orientador = $this->docente('Profa. Ana Menezes');
        Orientacao::create([
            'trabalho_academico_id' => $this->trabalho->id, 'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $orientador->id, 'tipo' => 'orientador', 'ordem' => 1, 'ativo' => true,
        ]);
        $this->banca = Banca::create([
            'trabalho_academico_id' => $this->trabalho->id, 'tipo' => 'defesa', 'status' => 'cadastrada',
            'data_hora' => '2026-03-12 14:00:00', 'local' => 'Auditório Central',
        ]);
        foreach (['Carlos Lima', 'Helena Souza'] as $i => $nome) {
            BancaMembro::create([
                'banca_id' => $this->banca->id, 'docente_programa_vinculo_id' => $this->docente($nome)->id,
                'papel' => 'avaliador', 'ordem' => $i + 1, 'ativo' => true,
            ]);
        }
    }

    private function docente(string $nome): DocenteProgramaVinculo
    {
        $profile = DocenteProfile::create([
            'user_id' => User::factory()->docente()->create(['name' => $nome])->id,
            'instituicao_sigla' => 'UNEB', 'ativo' => true,
        ]);

        return DocenteProgramaVinculo::create([
            'programa_id' => $this->programa->id, 'docente_profile_id' => $profile->id,
            'tipo_vinculo' => 'interno', 'pode_orientar' => true, 'pode_avaliar' => true, 'ativo' => true,
        ]);
    }

    private function coorientador(string $nome): void
    {
        Orientacao::create([
            'trabalho_academico_id' => $this->trabalho->id,
            'discente_programa_vinculo_id' => $this->trabalho->discente_programa_vinculo_id,
            'docente_programa_vinculo_id' => $this->docente($nome)->id, 'tipo' => 'coorientador', 'ordem' => 2, 'ativo' => true,
        ]);
    }

    private function elementos(array $sobrescrever = []): array
    {
        return array_merge([
            E::TITULO_PT => '{\em Modelagem} em sa\\\'ude na Bahia',
            E::TITULO_EN => 'Health modelling in Bahia',
            E::RESUMO_PT => 'Resumo do trabalho.',
            E::PALAVRAS_CHAVE_PT => 'modelagem; saúde',
            E::ABSTRACT_EN => 'Abstract of the work.',
            E::PALAVRAS_CHAVE_EN => 'modelling; health',
            E::AUTOR => 'Souza, Maria',
            E::ORIENTADOR => 'Prof. Dra. Ana Menezes',
            E::COORIENTADORES => [],
            E::MEMBROS_BANCA => ['Carlos Lima', 'Helena Souza'],
            E::DATA_DEFESA => '12 de março de 2026',
            E::LOCAL_DEFESA => 'Auditório Central',
        ], $sobrescrever);
    }

    private function item(array $itens, string $codigo): array
    {
        foreach ($itens as $item) {
            if ($item['codigo'] === $codigo) {
                return $item;
            }
        }
        $this->fail("Item $codigo ausente.");
    }

    private function conferir(array $elementos, string $tipo = 'defesa', string $idioma = 'pt', ?Banca $banca = null): array
    {
        return $this->servico->conferir($elementos, $this->trabalho->fresh(), $banca ?? $this->banca, $tipo, $idioma);
    }

    public function test_tudo_presente_e_igual_nao_bloqueia(): void
    {
        $itens = $this->conferir($this->elementos());

        $this->assertFalse($this->servico->bloqueia($itens));
        foreach ($itens as $item) {
            $this->assertSame('ok', $item['status'], $item['codigo']);
        }
        $this->assertSame('Modelagem em saúde na Bahia', $this->item($itens, E::TITULO_PT)['valor_tex']);
    }

    public function test_sem_resumo_bloqueia(): void
    {
        $itens = $this->conferir($this->elementos([E::RESUMO_PT => null]));

        $this->assertSame('ausente', $this->item($itens, E::RESUMO_PT)['status']);
        $this->assertTrue($this->item($itens, E::RESUMO_PT)['bloqueia']);
        $this->assertTrue($this->servico->bloqueia($itens));
    }

    public function test_sem_membros_banca_bloqueia(): void
    {
        $itens = $this->conferir($this->elementos([E::MEMBROS_BANCA => []]));

        $this->assertSame('ausente', $this->item($itens, E::MEMBROS_BANCA)['status']);
        $this->assertTrue($this->servico->bloqueia($itens));
    }

    public function test_sem_coorientador_bloqueia_so_quando_ha_coorientador_no_app(): void
    {
        $semCoorientadorNoApp = $this->conferir($this->elementos());
        $this->assertFalse($this->servico->bloqueia($semCoorientadorNoApp));

        $this->coorientador('Dr. Paulo Gildásio');

        $ausente = $this->conferir($this->elementos([E::COORIENTADORES => []]));
        $this->assertSame('ausente', $this->item($ausente, E::COORIENTADORES)['status']);
        $this->assertTrue($this->servico->bloqueia($ausente));

        $presente = $this->conferir($this->elementos([E::COORIENTADORES => ['Prof. Paulo Gild\\\'asio']]));
        $this->assertSame('ok', $this->item($presente, E::COORIENTADORES)['status']);
        $this->assertFalse($this->servico->bloqueia($presente));
    }

    public function test_membro_divergente_nao_bloqueia_e_lista_diferencas(): void
    {
        $itens = $this->conferir($this->elementos([E::MEMBROS_BANCA => ['Carlos Lima', 'Joana Prado']]));
        $membros = $this->item($itens, E::MEMBROS_BANCA);

        $this->assertSame('divergente', $membros['status']);
        $this->assertFalse($membros['bloqueia']);
        $this->assertSame(['Joana Prado'], $membros['diferencas']['somente_tex']);
        $this->assertSame(['Helena Souza'], $membros['diferencas']['somente_sistema']);
        $this->assertFalse($this->servico->bloqueia($itens));
    }

    public function test_divergencias_de_titulo_autor_data_e_local_nao_bloqueiam(): void
    {
        $itens = $this->conferir($this->elementos([
            E::TITULO_PT => 'Outro título',
            E::AUTOR => 'Fulano de Tal',
            E::DATA_DEFESA => '13/03/2026',
            E::LOCAL_DEFESA => 'Sala 9',
        ]));

        foreach ([E::TITULO_PT, E::AUTOR, E::DATA_DEFESA, E::LOCAL_DEFESA] as $codigo) {
            $this->assertSame('divergente', $this->item($itens, $codigo)['status'], $codigo);
        }
        $this->assertFalse($this->servico->bloqueia($itens));
    }

    public function test_qualificacao_sem_abstract_e_aviso(): void
    {
        $itens = $this->conferir($this->elementos([E::ABSTRACT_EN => null, E::PALAVRAS_CHAVE_EN => null, E::TITULO_EN => null]), 'qualificacao');

        foreach ([E::ABSTRACT_EN, E::PALAVRAS_CHAVE_EN, E::TITULO_EN] as $codigo) {
            $this->assertSame('aviso', $this->item($itens, $codigo)['status'], $codigo);
            $this->assertFalse($this->item($itens, $codigo)['bloqueia']);
        }
        $this->assertFalse($this->servico->bloqueia($itens));
    }

    public function test_idioma_en_inverte_obrigatorios(): void
    {
        $semEn = $this->conferir($this->elementos([E::ABSTRACT_EN => null, E::TITULO_EN => null]), 'defesa', 'en');
        $this->assertSame('ausente', $this->item($semEn, E::ABSTRACT_EN)['status']);
        $this->assertSame('ausente', $this->item($semEn, E::TITULO_EN)['status']);
        $this->assertTrue($this->servico->bloqueia($semEn));

        $semPt = $this->conferir($this->elementos([E::RESUMO_PT => null, E::PALAVRAS_CHAVE_PT => null, E::TITULO_PT => null]), 'defesa', 'en');
        $this->assertSame('aviso', $this->item($semPt, E::RESUMO_PT)['status']);
        $this->assertSame('aviso', $this->item($semPt, E::TITULO_PT)['status']);
        $this->assertFalse($this->servico->bloqueia($semPt));
    }

    public function test_sem_banca_so_confere_presenca(): void
    {
        $itens = $this->servico->conferir($this->elementos(), $this->trabalho->fresh(), null, 'defesa', 'pt');
        $this->assertSame('ok', $this->item($itens, E::MEMBROS_BANCA)['status']);
        $this->assertSame('ok', $this->item($itens, E::DATA_DEFESA)['status']);

        $vazio = $this->servico->conferir($this->elementos([E::MEMBROS_BANCA => []]), $this->trabalho->fresh(), null, 'defesa', 'pt');
        $this->assertTrue($this->servico->bloqueia($vazio));
    }
}
