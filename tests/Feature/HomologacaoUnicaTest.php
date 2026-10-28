<?php

namespace Tests\Feature;

use App\Mail\BancaAgendadaOrientacaoMail;
use App\Mail\HomologacaoDevolvidaMail;
use App\Mail\TrabalhoEnviadoBancaMail;
use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\Barema;
use App\Models\CriterioCpa;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\SubmissaoReferencia;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\TrabalhoSubmissaoLatex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HomologacaoUnicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fila_de_homologacao_lista_apenas_bancas_aguardando(): void
    {
        [$secretaria, , $bancaAguardando] = $this->criarCenarioCompleto();

        $bancaProposta = Banca::create([
            'trabalho_academico_id' => $bancaAguardando->trabalho_academico_id,
            'tipo' => 'defesa',
            'status' => 'cadastrada',
            'homologacao_status' => 'proposta',
        ]);

        $response = $this->actingAs($secretaria)->get(route('bancas.homologacao'));

        $response->assertOk();
        $response->assertSee(route('bancas.homologacao.show', $bancaAguardando), false);
        $response->assertDontSee(route('bancas.homologacao.show', $bancaProposta), false);
    }

    public function test_secretaria_salva_respostas_das_perguntas_da_cpa(): void
    {
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto();

        $c1 = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'pergunta' => 'Cumpre produção científica mínima?',
            'ordem' => 1,
            'ativo' => true,
        ]);
        $c2 = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => null,
            'pergunta' => 'Anexou comprovante de submissão?',
            'ordem' => 2,
            'ativo' => true,
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologacao.responder', $banca), [
                'respostas' => [
                    $c1->id => '1',
                    $c2->id => '0',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('homologacao_respostas_cpa', [
            'banca_id' => $banca->id,
            'criterio_cpa_id' => $c1->id,
            'tentativa' => 1,
            'resposta' => true,
            'respondido_por' => $secretaria->id,
        ]);
        $this->assertDatabaseHas('homologacao_respostas_cpa', [
            'banca_id' => $banca->id,
            'criterio_cpa_id' => $c2->id,
            'tentativa' => 1,
            'resposta' => false,
            'respondido_por' => $secretaria->id,
        ]);
    }

    public function test_homologacao_bloqueada_enquanto_falta_resposta_da_cpa(): void
    {
        Mail::fake();
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto();

        CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'pergunta' => 'Critério obrigatório 1?',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
        Mail::assertNothingSent();
    }

    public function test_homologacao_bloqueada_quando_qualquer_resposta_da_cpa_e_nao(): void
    {
        Mail::fake();
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto();

        $c1 = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'pergunta' => 'Critério 1?',
            'ordem' => 1,
            'ativo' => true,
        ]);
        $c2 = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'pergunta' => 'Critério 2?',
            'ordem' => 2,
            'ativo' => true,
        ]);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.responder', $banca), [
            'respostas' => [
                $c1->id => '1',
                $c2->id => '0',
            ],
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
        Mail::assertNothingSent();
    }

    public function test_homologacao_bloqueada_quando_composicao_da_banca_nao_cumprida(): void
    {
        Mail::fake();
        [$secretaria, , $banca] = $this->criarCenarioCompleto();

        // Remove o membro externo para violar a regra mínima (1 interno + 1 externo)
        $banca->membros()
            ->whereHas('docenteProgramaVinculo', fn ($q) => $q->where('tipo_vinculo', 'externo'))
            ->delete();

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_homologacao_bloqueada_quando_template_latex_nao_esta_conforme(): void
    {
        Mail::fake();
        [$secretaria, , $banca, , , , $submissao] = $this->criarCenarioCompleto();

        $submissao->update([
            'status' => 'nao_ok',
            'conformidade' => [
                'ok' => false,
                'checklist' => ['autor' => true, 'orientador' => false],
                'divergencias' => ['Orientador divergente do cadastro.'],
            ],
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_homologacao_dispara_email_aos_avaliadores_com_pdf_e_orientador_com_folha_e_ata(): void
    {
        Mail::fake();
        [$secretaria, $programa, $banca, , $orientador, $coorientador, , $avaliadores] = $this->criarCenarioCompleto();

        $criterio = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'pergunta' => 'Critério CPA atendido?',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $this->actingAs($secretaria)->post(route('bancas.homologacao.responder', $banca), [
            'respostas' => [$criterio->id => '1'],
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertRedirect();

        $banca->refresh();
        $this->assertSame('homologada', $banca->homologacao_status);
        $this->assertTrue($banca->libera_envio_trabalho);
        $this->assertSame($secretaria->id, $banca->homologada_por);
        $this->assertNotNull($banca->homologada_em);
        $this->assertNotNull($banca->enviada_banca_em);
        $this->assertNotNull($banca->documentoHomologacao->enviado_em);

        foreach ($avaliadores as $avaliadorUser) {
            Mail::assertSent(TrabalhoEnviadoBancaMail::class, fn ($m) => $m->hasTo($avaliadorUser->email));
        }

        Mail::assertSent(BancaAgendadaOrientacaoMail::class, fn ($m) => $m->hasTo($orientador->email));
        Mail::assertSent(BancaAgendadaOrientacaoMail::class, fn ($m) => $m->hasTo($coorientador->email));
    }

    public function test_homologacao_registra_envio_na_secretaria(): void
    {
        Mail::fake();
        [$secretaria, , $banca] = $this->criarCenarioCompleto();

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca));

        $this->actingAs($secretaria)
            ->get(route('bancas.homologacao'))
            ->assertOk()
            ->assertSee('Registro de homologações e envios realizados')
            ->assertSee('Homologada e enviada');

        $this->actingAs($secretaria)
            ->get(route('bancas.homologacao.show', $banca))
            ->assertOk()
            ->assertSee('Registro de Homologação e Envio Realizado')
            ->assertSee($secretaria->name);
    }

    public function test_email_ao_avaliador_contem_resumo_de_referencias_e_aviso_de_verificacao(): void
    {
        [, , $banca, , , , $submissao, $avaliadores] = $this->criarCenarioCompleto();

        $mail = new TrabalhoEnviadoBancaMail(
            banca: $banca,
            documento: $banca->documentoHomologacao,
            avaliador: $avaliadores[0],
            downloadUrl: 'https://exemplo.local/download',
            submissao: $submissao,
        );

        $html = $mail->render();

        $this->assertStringContainsString('Referências não localizadas devem ser verificadas', $html);
        $this->assertStringContainsString('https://exemplo.local/download', $html);
        $this->assertStringContainsString('Verificação automática do template', $html);
    }

    public function test_email_ao_orientador_contem_folha_de_assinaturas_latex_e_ata_de_assinatura(): void
    {
        [, , $banca, , $orientador] = $this->criarCenarioCompleto();

        $mail = new BancaAgendadaOrientacaoMail($banca, $orientador);
        $html = $mail->render();
        $attachments = $mail->attachments();

        $this->assertStringContainsString(route('avaliacoes.folha-aprovacao', $banca), $html);
        $this->assertStringContainsString(route('avaliacoes.ata', $banca), $html);
        $this->assertCount(2, $attachments);
    }

    public function test_devolucao_exige_observacoes_e_notifica_discente_e_orientacao(): void
    {
        Mail::fake();
        [$secretaria, , $banca, $discente, $orientador, $coorientador] = $this->criarCenarioCompleto();

        $this->actingAs($secretaria)
            ->post(route('bancas.devolver', $banca), ['observacoes' => ''])
            ->assertSessionHasErrors('observacoes');

        $this->actingAs($secretaria)
            ->post(route('bancas.devolver', $banca), [
                'observacoes' => 'Ajustar resumo e comprovar submissão de artigo.',
            ])
            ->assertRedirect();

        $banca->refresh();
        $this->assertSame('devolvida', $banca->homologacao_status);
        $this->assertSame('Ajustar resumo e comprovar submissão de artigo.', $banca->homologacao_observacoes);
        $this->assertSame($secretaria->id, $banca->devolvida_por);

        Mail::assertSent(HomologacaoDevolvidaMail::class, fn ($m) => $m->hasTo($discente->email));
        Mail::assertSent(HomologacaoDevolvidaMail::class, fn ($m) => $m->hasTo($orientador->email));
        Mail::assertSent(HomologacaoDevolvidaMail::class, fn ($m) => $m->hasTo($coorientador->email));
    }

    public function test_reenvio_cria_nova_tentativa_mantendo_historico_das_respostas_anteriores(): void
    {
        Mail::fake();
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto();

        $criterio = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => 'mestrado',
            'pergunta' => 'Critério CPA?',
            'ordem' => 1,
            'ativo' => true,
        ]);

        // Tentativa 1: respondeu Não e devolveu
        $this->actingAs($secretaria)->post(route('bancas.homologacao.responder', $banca), [
            'respostas' => [$criterio->id => '0'],
        ]);
        $this->actingAs($secretaria)->post(route('bancas.devolver', $banca), [
            'observacoes' => 'Corrigir item da CPA.',
        ]);

        // Discente reenvia: tentativa 2
        $banca->refresh()->update([
            'homologacao_status' => 'aguardando',
            'homologacao_tentativa' => 2,
        ]);

        // Na tentativa 2, ainda não há resposta
        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        // Secretaria responde Sim na tentativa 2
        $this->actingAs($secretaria)->post(route('bancas.homologacao.responder', $banca), [
            'respostas' => [$criterio->id => '1'],
        ]);

        // Histórico preserva tentativa 1 (false) e tentativa 2 (true)
        $this->assertDatabaseHas('homologacao_respostas_cpa', [
            'banca_id' => $banca->id,
            'criterio_cpa_id' => $criterio->id,
            'tentativa' => 1,
            'resposta' => false,
        ]);
        $this->assertDatabaseHas('homologacao_respostas_cpa', [
            'banca_id' => $banca->id,
            'criterio_cpa_id' => $criterio->id,
            'tentativa' => 2,
            'resposta' => true,
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertRedirect();

        $this->assertSame('homologada', $banca->fresh()->homologacao_status);
    }

    public function test_quando_nao_ha_perguntas_da_cpa_ativas_banca_pode_ser_homologada_direto(): void
    {
        Mail::fake();
        [$secretaria, , $banca] = $this->criarCenarioCompleto();

        $this->actingAs($secretaria)
            ->get(route('bancas.homologacao.show', $banca))
            ->assertOk()
            ->assertSee('Nenhuma pergunta da CPA ativa');

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertRedirect();

        $this->assertSame('homologada', $banca->fresh()->homologacao_status);
    }

    public function test_banca_ja_homologada_nao_e_homologada_de_novo_nem_reenvia_emails(): void
    {
        [$secretaria, , $banca] = $this->criarCenarioCompleto();

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))->assertRedirect();
        $homologadaEm = $banca->fresh()->homologada_em;

        Mail::fake();
        $this->travel(1)->hours();

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        $this->assertEquals($homologadaEm, $banca->fresh()->homologada_em);
        Mail::assertNothingSent();
    }

    public function test_banca_cancelada_nao_pode_ser_homologada(): void
    {
        Mail::fake();
        [$secretaria, , $banca] = $this->criarCenarioCompleto();
        $banca->update(['status' => 'cancelada']);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
        Mail::assertNothingSent();
    }

    public function test_banca_homologada_nao_pode_ser_devolvida(): void
    {
        [$secretaria, , $banca] = $this->criarCenarioCompleto();
        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca))->assertRedirect();

        Mail::fake();

        $this->actingAs($secretaria)
            ->post(route('bancas.devolver', $banca), ['observacoes' => 'Tarde demais.'])
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('homologada', $banca->fresh()->homologacao_status);
        Mail::assertNothingSent();
    }

    public function test_respostas_da_cpa_nao_mudam_depois_da_decisao(): void
    {
        [$secretaria, $programa, $banca] = $this->criarCenarioCompleto();

        $criterio = CriterioCpa::create([
            'programa_id' => $programa->id,
            'etapa' => 'qualificacao',
            'nivel' => null,
            'pergunta' => 'Critério?',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $this->actingAs($secretaria)
            ->post(route('bancas.homologar', $banca), ['respostas' => [$criterio->id => '1']])
            ->assertRedirect();

        $this->actingAs($secretaria)
            ->post(route('bancas.homologacao.responder', $banca), ['respostas' => [$criterio->id => '0']])
            ->assertSessionHasErrors('homologacao');

        $this->assertDatabaseHas('homologacao_respostas_cpa', [
            'banca_id' => $banca->id,
            'criterio_cpa_id' => $criterio->id,
            'resposta' => true,
        ]);
    }

    public function test_avaliador_nao_abre_barema_antes_da_homologacao(): void
    {
        [, $programa, $banca, , , , , $avaliadores] = $this->criarCenarioCompleto();

        $barema = Barema::create([
            'programa_id' => $programa->id,
            'nome' => 'Barema Qualificação',
            'tipo' => 'qualificacao',
            'nivel' => 'mestrado',
            'versao' => 1,
            'ativo' => true,
        ]);

        $this->actingAs($avaliadores[0])
            ->get(route('avaliacoes.form', [$banca, $barema]))
            ->assertRedirect(route('avaliacoes.banca', $banca))
            ->assertSessionHasErrors('status');
    }

    public function test_avaliador_ve_relatorio_de_referencias_na_tela_de_avaliacao(): void
    {
        Mail::fake();
        [$secretaria, , $banca, , , , $submissao, $avaliadores] = $this->criarCenarioCompleto();

        SubmissaoReferencia::create([
            'submissao_id' => $submissao->id,
            'chave' => 'silva2024',
            'texto' => 'Silva, J. (2024). Modelagem Computacional em Saúde Pública.',
            'titulo_tex' => 'Modelagem Computacional em Saúde Pública',
            'ano_tex' => 2024,
            'status' => 'nao_localizada',
        ]);

        $this->actingAs($secretaria)->post(route('bancas.homologar', $banca));

        $this->actingAs($avaliadores[0])
            ->get(route('avaliacoes.banca', $banca))
            ->assertOk()
            ->assertSee('Verificação das referências')
            ->assertSee('não localizada — verificar')
            ->assertSee('Modelagem Computacional em Saúde Pública');
    }

    public function test_secretaria_nao_ve_relatorio_de_referencias_nas_telas_de_homologacao(): void
    {
        [$secretaria, , $banca, , , , $submissao] = $this->criarCenarioCompleto();

        SubmissaoReferencia::create([
            'submissao_id' => $submissao->id,
            'chave' => 'silva2024',
            'texto' => 'Silva, J. (2024). Referencia Secreta Para Avaliadores.',
            'titulo_tex' => 'Referencia Secreta Para Avaliadores',
            'ano_tex' => 2024,
            'status' => 'nao_localizada',
        ]);

        foreach ([route('bancas.homologacao'), route('bancas.homologacao.show', $banca)] as $url) {
            $response = $this->actingAs($secretaria)->get($url);
            $response->assertOk();
            $response->assertDontSee('Relatório de verificação das referências');
            $response->assertDontSee('Verificação das referências');
            $response->assertDontSee('atualidade');
            $response->assertDontSee('Referencia Secreta Para Avaliadores');
        }
    }

    private function criarCenarioCompleto(): array
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
        $profile = DiscenteProfile::create([
            'user_id' => $discente->id,
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
            'titulo' => 'Dissertação de Teste em LaTeX',
            'nivel' => 'mestrado',
            'status' => 'em_andamento',
        ]);

        // Orientador e Coorientador
        $orientadorUser = User::factory()->docente()->create(['name' => 'Prof. Orientador']);
        $orientadorProfile = DocenteProfile::create([
            'user_id' => $orientadorUser->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UNEB',
            'instituicao_sigla' => 'UNEB',
            'ativo' => true,
        ]);
        $orientadorVinculo = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $orientadorProfile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $orientadorVinculo->id,
            'tipo' => 'orientador',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $coorientadorUser = User::factory()->docente()->create(['name' => 'Prof. Coorientador']);
        $coorientadorProfile = DocenteProfile::create([
            'user_id' => $coorientadorUser->id,
            'titulacao' => 'Doutorado',
            'instituicao_origem' => 'UNEB',
            'instituicao_sigla' => 'UNEB',
            'ativo' => true,
        ]);
        $coorientadorVinculo = DocenteProgramaVinculo::create([
            'programa_id' => $programa->id,
            'docente_profile_id' => $coorientadorProfile->id,
            'tipo_vinculo' => 'interno',
            'pode_orientar' => true,
            'pode_avaliar' => false,
            'ativo' => true,
        ]);
        Orientacao::create([
            'trabalho_academico_id' => $trabalho->id,
            'discente_programa_vinculo_id' => $vinculo->id,
            'docente_programa_vinculo_id' => $coorientadorVinculo->id,
            'tipo' => 'coorientador',
            'ordem' => 2,
            'ativo' => true,
        ]);

        // Submissão LaTeX + TrabalhoDocumento
        $submissao = TrabalhoSubmissaoLatex::create([
            'trabalho_academico_id' => $trabalho->id,
            'enviado_por_id' => $discente->id,
            'tipo_documento' => 'qualificacao',
            'idioma' => 'pt',
            'zip_path' => 'latex/teste.zip',
            'zip_hash_sha256' => str_repeat('b', 64),
            'tex_principal' => 'main.tex',
            'pdf_banca_path' => 'latex/teste.pdf',
            'status' => 'ok',
            'conformidade' => [
                'ok' => true,
                'checklist' => [
                    'autor' => true,
                    'orientador' => true,
                    'titulo' => true,
                    'resumo_pt' => true,
                    'abstract_en' => true,
                ],
                'divergencias' => [],
            ],
            'refs_status' => 'concluida',
            'refs_total' => 5,
            'refs_com_ano' => 5,
            'refs_recentes_pct' => 80.0,
            'refs_atualizadas' => true,
            'refs_orientador' => 1,
        ]);

        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'submissao_latex_id' => $submissao->id,
            'enviado_por_id' => $discente->id,
            'tipo_documento' => 'qualificacao',
            'versao' => 1,
            'arquivo_pdf_path' => 'latex/teste.pdf',
            'arquivo_pdf_nome_original' => 'main.pdf',
            'arquivo_pdf_mime' => 'application/pdf',
            'arquivo_pdf_tamanho' => 2048,
            'arquivo_pdf_hash_sha256' => str_repeat('c', 64),
            'status_analise' => 'concluida',
        ]);

        $banca = Banca::create([
            'trabalho_academico_id' => $trabalho->id,
            'documento_homologacao_id' => $documento->id,
            'tipo' => 'qualificacao',
            'status' => 'cadastrada',
            'homologacao_status' => 'aguardando',
            'homologacao_tentativa' => 1,
            'libera_envio_trabalho' => false,
            'data_hora' => now()->addDays(15),
            'local' => 'Auditório PPGMSB',
        ]);

        $avaliadoresUsers = [];
        foreach ([['interno', 'UNEB', 'UNEB'], ['externo', 'UFBA', 'UFBA']] as $ordem => [$tipo, $sigla, $nome]) {
            $user = User::factory()->docente()->create();
            $avaliadoresUsers[] = $user;
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

        return [
            $secretaria,
            $programa,
            $banca->fresh(),
            $discente,
            $orientadorUser,
            $coorientadorUser,
            $submissao,
            $avaliadoresUsers,
        ];
    }
}
