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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomologacaoBancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_homologa_banca_com_evidencia_obrigatoria_validada(): void
    {
        [$secretaria, $programa, $banca] = $this->estrutura();
        $criterioId = DB::table('criterios_cpa')->insertGetId($this->criterio($programa->id, $secretaria->id));
        DB::table('banca_evidencias')->insert([
            'banca_id' => $banca->id,
            'criterio_cpa_id' => $criterioId,
            'status' => 'validada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($secretaria)->post(route('bancas.homologar', $banca));

        $response->assertRedirect();
        $this->assertDatabaseHas('bancas', [
            'id' => $banca->id,
            'homologacao_status' => 'homologada',
            'libera_envio_trabalho' => true,
            'homologada_por' => $secretaria->id,
        ]);
    }

    public function test_banca_nao_e_homologada_sem_evidencia_obrigatoria(): void
    {
        [$secretaria, $programa, $banca] = $this->estrutura();
        DB::table('criterios_cpa')->insert($this->criterio($programa->id, $secretaria->id));

        $response = $this->actingAs($secretaria)->post(route('bancas.homologar', $banca));

        $response->assertRedirect()->assertSessionHasErrors('homologacao');
        $this->assertDatabaseHas('bancas', [
            'id' => $banca->id,
            'homologacao_status' => 'aguardando',
            'libera_envio_trabalho' => false,
        ]);
    }

    public function test_secretaria_devolve_banca_com_justificativa(): void
    {
        [$secretaria, , $banca] = $this->estrutura();

        $response = $this->actingAs($secretaria)->post(route('bancas.devolver', $banca), [
            'observacoes' => 'Inclua o registro de produção informado no requisito CPA-DEF-01.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bancas', [
            'id' => $banca->id,
            'homologacao_status' => 'devolvida',
            'homologacao_observacoes' => 'Inclua o registro de produção informado no requisito CPA-DEF-01.',
            'libera_envio_trabalho' => false,
            'devolvida_por' => $secretaria->id,
        ]);
    }

    public function test_docente_nao_acessa_fila_de_homologacao(): void
    {
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('bancas.homologacao'))->assertStatus(403);
    }

    public function test_defesa_nao_e_criada_apos_qualificacao_reprovada(): void
    {
        [$secretaria, $programa, $trabalho, $qualificacao] = $this->estruturaComComposicao();
        $qualificacao->update([
            'status' => 'finalizada',
            'resultado' => 'reprovado',
            'homologacao_status' => 'homologada',
            'libera_envio_trabalho' => true,
        ]);
        $avaliadores = DocenteProgramaVinculo::where('programa_id', $programa->id)
            ->where('pode_avaliar', true)
            ->get();

        $response = $this->actingAs($secretaria)->post(route('bancas.store'), [
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
            'status' => 'cadastrada',
            'quantidade_avaliadores' => 2,
            'avaliador_interno_id' => $avaliadores->firstWhere('tipo_vinculo', 'interno')->id,
            'avaliador_externo_id' => $avaliadores->firstWhere('tipo_vinculo', 'externo')->id,
        ]);

        $response->assertSessionHasErrors('tipo');
        $this->assertDatabaseMissing('bancas', [
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
        ]);
    }

    public function test_banca_sem_orcid_do_discente_nao_e_homologada(): void
    {
        [$secretaria, , $banca] = $this->estrutura(orcid: null);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_tela_de_homologacao_mostra_quadro_de_verificacao(): void
    {
        [$secretaria] = $this->estrutura(orcid: null);

        $this->actingAs($secretaria)->get(route('bancas.homologacao'))
            ->assertOk()
            ->assertSee('ORCID do discente')
            ->assertSee('Número de avaliadores');
    }

    public function test_cadastro_de_banca_sem_orcid_do_discente_e_recusado(): void
    {
        [$secretaria, $programa, $trabalho, $qualificacao] = $this->estruturaComComposicao(orcid: null);
        $qualificacao->update(['status' => 'finalizada', 'resultado' => 'aprovado']);

        $this->actingAs($secretaria)->post(route('bancas.store'), $this->dadosDefesa($programa, $trabalho))
            ->assertSessionHasErrors('avaliadores');
        $this->assertDatabaseMissing('bancas', ['trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa']);
    }

    public function test_cadastro_de_banca_segue_regra_do_curso(): void
    {
        [$secretaria, $programa, $trabalho, $qualificacao] = $this->estruturaComComposicao();
        $qualificacao->update(['status' => 'finalizada', 'resultado' => 'aprovado']);
        $curso = app(\App\Services\CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');
        $curso->regrasBanca()->where('etapa', 'defesa')->update(['min_avaliadores' => 3]);
        $trabalho->discenteVinculo->update(['programa_curso_id' => $curso->id]);

        $this->actingAs($secretaria)->post(route('bancas.store'), $this->dadosDefesa($programa, $trabalho))
            ->assertSessionHasErrors('avaliadores');

        $curso->regrasBanca()->where('etapa', 'defesa')->update(['min_avaliadores' => 2]);
        $this->actingAs($secretaria)->post(route('bancas.store'), $this->dadosDefesa($programa, $trabalho))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bancas', ['trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa']);
    }

    public function test_formulario_de_banca_mostra_regras_com_siglas_do_cadastro(): void
    {
        [$secretaria, , $trabalho] = $this->estruturaComComposicao();

        $this->actingAs($secretaria)->get(route('bancas.create', ['trabalho_id' => $trabalho->id]))
            ->assertOk()
            ->assertSee('Regras do curso')
            ->assertSee('externo(s) à UNEB', false)
            ->assertSee('interno(s) ao PPGMSB', false);
    }

    public function test_cadastro_de_banca_aceita_composicao_permitida_pela_regra_do_curso(): void
    {
        [$secretaria, $programa, $trabalho, $qualificacao] = $this->estruturaComComposicao();
        $qualificacao->update(['status' => 'finalizada', 'resultado' => 'aprovado']);
        $curso = app(\App\Services\CursoProgramaService::class)->criar($programa, 'mestrado', 'profissional');
        $curso->regrasBanca()->where('etapa', 'defesa')->update([
            'min_avaliadores' => 1, 'max_avaliadores' => 1, 'min_internos' => 0, 'min_externos_instituicao' => 1,
        ]);
        $trabalho->discenteVinculo->update(['programa_curso_id' => $curso->id]);
        $externo = DocenteProgramaVinculo::where('programa_id', $programa->id)->where('tipo_vinculo', 'externo')->firstOrFail();

        $this->actingAs($secretaria)->post(route('bancas.store'), [
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
            'status' => 'cadastrada',
            'quantidade_avaliadores' => 1,
            'avaliador_externo_id' => $externo->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bancas', ['trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa']);
    }

    private function dadosDefesa(Programa $programa, TrabalhoAcademico $trabalho): array
    {
        $avaliadores = DocenteProgramaVinculo::where('programa_id', $programa->id)->where('pode_avaliar', true)->get();

        return [
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'defesa',
            'status' => 'cadastrada',
            'quantidade_avaliadores' => 2,
            'avaliador_interno_id' => $avaliadores->firstWhere('tipo_vinculo', 'interno')->id,
            'avaliador_externo_id' => $avaliadores->firstWhere('tipo_vinculo', 'externo')->id,
        ];
    }

    private function estrutura(?string $orcid = 'https://orcid.org/0000-0002-1825-0097'): array
    {
        $secretaria = User::factory()->secretaria()->create();
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

        $discente = User::factory()->discente()->create();
        $profile = DiscenteProfile::create(['user_id' => $discente->id, 'orcid_url' => $orcid, 'ativo' => true]);
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
        $banca = Banca::create([
            'trabalho_academico_id' => $trabalho->id,
            'tipo' => 'qualificacao',
            'status' => 'cadastrada',
            'libera_envio_trabalho' => false,
        ]);
        DB::table('bancas')->where('id', $banca->id)->update([
            'homologacao_status' => 'aguardando',
        ]);

        foreach ([['interno', 'UNEB', 'UNEB'], ['externo', 'UFBA', 'UFBA']] as $ordem => [$tipo, $sigla, $nome]) {
            $user = User::factory()->docente()->create();
            $docenteProfile = DocenteProfile::create([
                'user_id' => $user->id,
                'titulacao' => 'Doutorado',
                'instituicao_origem' => $nome,
                'instituicao_sigla' => $sigla,
                'ativo' => true,
            ]);
            $vinculoDocente = DocenteProgramaVinculo::create([
                'programa_id' => $programa->id,
                'docente_profile_id' => $docenteProfile->id,
                'tipo_vinculo' => $tipo,
                'pode_orientar' => false,
                'pode_avaliar' => true,
                'ativo' => true,
            ]);
            BancaMembro::create([
                'banca_id' => $banca->id,
                'docente_programa_vinculo_id' => $vinculoDocente->id,
                'papel' => 'avaliador',
                'ordem' => $ordem + 1,
                'ativo' => true,
            ]);
        }

        return [$secretaria, $programa, $banca->fresh()];
    }

    private function criterio(int $programaId, int $secretariaId): array
    {
        return [
            'programa_id' => $programaId,
            'criado_por' => $secretariaId,
            'codigo' => 'CPA-DEF-01',
            'titulo' => 'Produção científica',
            'descricao' => 'Apresente o DOI da produção.',
            'etapa' => 'qualificacao',
            'tipo_evidencia' => 'doi',
            'obrigatorio' => true,
            'nivel' => null,
            'ordem' => 1,
            'versao' => '1.0',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function estruturaComComposicao(?string $orcid = 'https://orcid.org/0000-0002-1825-0097'): array
    {
        [$secretaria, $programa, $banca] = $this->estrutura($orcid);
        $trabalho = $banca->trabalhoAcademico;

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

        return [$secretaria, $programa, $trabalho, $banca];
    }
}
