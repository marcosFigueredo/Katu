<?php

namespace Tests\Feature;

use App\Models\AtividadeFormativa;
use App\Models\AtividadeRealizada;
use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\Barema;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\EspelhoDiscente;
use App\Models\Instituicao;
use App\Models\Orientacao;
use App\Models\ProducaoPublicacao;
use App\Models\ProgramaCurso;
use App\Models\Programa;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CriaDadosPesquisa;
use Tests\Concerns\CriaItensDaDefesa;
use Tests\TestCase;

class PedidoHomologacaoTest extends TestCase
{
    use CriaDadosPesquisa;
    use CriaItensDaDefesa;
    use RefreshDatabase;

    public function test_discente_propoe_banca_que_fica_proposta_e_fora_da_fila(): void
    {
        Mail::fake();
        [$discente, $secretaria, $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho))
            ->assertRedirect(route('meu-trabalho.index'));

        $banca = Banca::where('trabalho_academico_id', $trabalho->id)->firstOrFail();
        $this->assertSame('proposta', $banca->homologacao_status);
        $this->assertFalse($banca->libera_envio_trabalho);
        $this->assertSame(1, (int) $banca->homologacao_tentativa);
        $this->assertNull($banca->documento_homologacao_id);
        Mail::assertNothingSent();

        $this->actingAs($secretaria)->get(route('bancas.index'))
            ->assertOk()
            ->assertDontSee(route('bancas.homologacao.show', $banca), false);
    }

    public function test_discente_envia_pdf_com_banca_proposta(): void
    {
        Storage::fake('local');
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trabalho_documentos', 1);
        $this->assertSame('proposta', Banca::firstOrFail()->homologacao_status);
    }

    public function test_envio_pdf_sem_banca_proposta_e_recusado(): void
    {
        Storage::fake('local');
        [$discente, , $trabalho] = $this->estrutura();

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasErrors(['pdf' => 'Proponha a banca antes de enviar o trabalho.']);

        // Banca já na fila da Secretaria também não aceita novo arquivo.
        $banca = $this->proporBanca($discente, $trabalho);
        $banca->update(['homologacao_status' => 'aguardando']);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasErrors(['pdf' => 'Proponha a banca antes de enviar o trabalho.']);
        $this->assertDatabaseCount('trabalho_documentos', 0);
    }

    public function test_enviar_homologacao_coloca_banca_aguardando_com_documento(): void
    {
        Mail::fake();
        [$discente, $secretaria, $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $banca->refresh();
        $this->assertSame('aguardando', $banca->homologacao_status);
        $this->assertSame($documento->id, (int) $banca->documento_homologacao_id);
        $this->assertSame(1, (int) $banca->homologacao_tentativa);
        $this->assertFalse($banca->libera_envio_trabalho);
        Mail::assertNothingSent();

        $this->actingAs($secretaria)->get(route('bancas.index'))
            ->assertOk()
            ->assertSee(route('bancas.homologacao.show', $banca), false);
    }

    public function test_enviar_homologacao_exige_dados_da_pesquisa(): void
    {
        Mail::fake();
        [$discente, , $trabalho] = $this->estrutura();
        $trabalho->dadosPesquisa()->delete();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors([
                'homologacao' => 'Preencha os dados da pesquisa (código, dados e ética) em Meu trabalho antes de enviar à homologação.',
            ]);
        $this->assertSame('proposta', $banca->fresh()->homologacao_status);

        $this->criarDadosPesquisa($trabalho);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_enviar_homologacao_guarda_copia_dos_dados_da_pesquisa_na_banca(): void
    {
        Mail::fake();
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();

        // Editar depois não muda o que foi enviado com o pedido.
        $trabalho->dadosPesquisa()->update(['codigo' => 'publico', 'codigo_link' => 'https://github.com/x/y']);

        $copia = $banca->fresh()->dados_pesquisa_enviados;
        $this->assertSame('nao_houve', $copia['codigo']);
        $this->assertNull($copia['codigo_link']);
        $this->assertFalse($copia['seres_humanos']);
        $this->assertArrayNotHasKey('id', $copia);
        $this->assertArrayNotHasKey('trabalho_academico_id', $copia);
    }

    public function test_enviar_homologacao_exige_composicao_valida(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        BancaMembro::where('banca_id', $banca->id)->limit(1)->update(['ativo' => false]);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('homologacao');

        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
        $this->assertNull($banca->fresh()->documento_homologacao_id);
    }

    public function test_enviar_homologacao_exige_orcid_do_discente(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $discente->discenteProfile->update(['orcid_url' => null]);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('orcid');

        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
    }

    public function test_enviar_homologacao_de_documento_alheio_e_proibido(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $outro = User::factory()->discente()->create();

        $this->actingAs($outro)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertStatus(403);
    }

    public function test_discente_nao_edita_banca_aguardando(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        // Em "proposta" o discente edita.
        $this->actingAs($discente)->get(route('bancas.edit', $banca))->assertOk();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $this->documento($trabalho, $discente)))
            ->assertSessionHasNoErrors();

        $this->actingAs($discente)->get(route('bancas.edit', $banca))->assertStatus(403);
        $this->actingAs($discente)->put(route('bancas.update', $banca), $this->dadosQualificacao($trabalho, local: 'Sala 9'))
            ->assertStatus(403);
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_reenvio_apos_devolucao_incrementa_tentativa(): void
    {
        [$discente, $secretaria, $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento));

        $this->actingAs($secretaria)->post(route('bancas.devolver', $banca), ['observacoes' => 'Ajuste o local.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('devolvida', $banca->fresh()->homologacao_status);

        // O discente corrige o mesmo pedido; a banca segue devolvida até o reenvio.
        $this->actingAs($discente)->put(route('bancas.update', $banca), $this->dadosQualificacao($trabalho, local: 'Sala 2'))
            ->assertRedirect(route('meu-trabalho.index'));
        $this->assertSame('devolvida', $banca->fresh()->homologacao_status);
        $this->assertSame('Sala 2', $banca->fresh()->local);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee('Ajuste o local.')
            ->assertSee('Enviar à homologação');

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();

        $banca->refresh();
        $this->assertSame('aguardando', $banca->homologacao_status);
        $this->assertSame(2, (int) $banca->homologacao_tentativa);
    }

    public function test_enviar_homologacao_com_banca_aguardando_e_recusado(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento));

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors('homologacao');
        $this->assertSame(1, (int) $banca->fresh()->homologacao_tentativa);
    }

    public function test_tela_meu_trabalho_mostra_estado_do_pedido(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);

        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertOk()
            ->assertSee('Proposta — envie o trabalho')
            ->assertDontSee('Enviar à homologação');

        $this->documento($trabalho, $discente);
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee('Enviar à homologação');

        $banca->update(['homologacao_status' => 'aguardando']);
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee('Aguardando homologação')
            ->assertDontSee('Enviar à homologação');
    }

    public function test_enviar_homologacao_exige_documento_do_tipo_da_banca(): void
    {
        [$discente, , $trabalho] = $this->estrutura();
        $qualificacao = $this->proporBanca($discente, $trabalho);
        $docQualificacao = $this->documento($trabalho, $discente);
        $docDissertacao = $this->documento($trabalho, $discente, 'defesa'); // enum do banco: qualificacao|defesa|...

        // Banca de qualificação não aceita a dissertação, e o botão só aparece no documento certo.
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $docDissertacao))
            ->assertSessionHasErrors(['homologacao' => 'Para a banca de qualificação, envie o documento de qualificação.']);
        $this->assertSame('proposta', $qualificacao->fresh()->homologacao_status);
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee(route('meu-trabalho.enviar-homologacao', $docQualificacao), false)
            ->assertDontSee(route('meu-trabalho.enviar-homologacao', $docDissertacao), false);

        // Banca de defesa não aceita o PDF antigo da qualificação.
        $qualificacao->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada']);
        $this->actingAs($discente)->post(route('bancas.store'), ['tipo' => 'defesa'] + $this->dadosQualificacao($trabalho))
            ->assertSessionHasNoErrors();
        $defesa = Banca::where('trabalho_academico_id', $trabalho->id)->where('tipo', 'defesa')->firstOrFail();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $docQualificacao))
            ->assertSessionHasErrors(['homologacao' => 'Para a banca de defesa, envie a dissertação ou a tese, não o documento de qualificação.']);
        $this->assertSame('proposta', $defesa->fresh()->homologacao_status);
        $this->actingAs($discente)->get(route('meu-trabalho.index'))
            ->assertSee(route('meu-trabalho.enviar-homologacao', $docDissertacao), false)
            ->assertDontSee(route('meu-trabalho.enviar-homologacao', $docQualificacao), false);

        // A defesa exige o espelho do discente antes do pedido.
        $barema = Barema::create([
            'programa_id' => $trabalho->programa_id, 'nome' => 'Defesa', 'tipo' => 'defesa',
            'nivel' => 'ambos', 'versao' => '1', 'ativo' => true,
        ]);
        EspelhoDiscente::create(['banca_id' => $defesa->id, 'barema_id' => $barema->id, 'nota_final' => 8, 'enviada_em' => now()]);
        $this->criarItensDaDefesa($trabalho);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $docDissertacao))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $defesa->fresh()->homologacao_status);
        $this->assertSame($docDissertacao->id, (int) $defesa->fresh()->documento_homologacao_id);
    }

    public function test_banca_cadastrada_pela_secretaria_tambem_passa_pela_homologacao(): void
    {
        Storage::fake('local');
        [$discente, $secretaria, $trabalho] = $this->estrutura();

        $this->actingAs($secretaria)->post(route('bancas.store'), [
            'trabalho_academico_id' => $trabalho->id,
            'status' => 'cadastrada',
        ] + $this->dadosQualificacao($trabalho))->assertSessionHasNoErrors();

        $banca = Banca::where('trabalho_academico_id', $trabalho->id)->firstOrFail();
        $this->assertSame('proposta', $banca->homologacao_status);
        $this->assertFalse($banca->libera_envio_trabalho);
        $this->assertNull($banca->homologada_por);

        // Editar pela Secretaria não homologa a banca.
        $this->actingAs($secretaria)->put(route('bancas.update', $banca), [
            'trabalho_academico_id' => $trabalho->id,
            'status' => 'cadastrada',
        ] + $this->dadosQualificacao($trabalho, local: 'Auditório'))->assertSessionHasNoErrors();
        $this->assertSame('proposta', $banca->fresh()->homologacao_status);
        $this->assertSame('Auditório', $banca->fresh()->local);

        $this->actingAs($discente)->post(route('meu-trabalho.store'), $this->dadosPdf())
            ->assertSessionHasNoErrors();
        $documento = TrabalhoDocumento::where('trabalho_academico_id', $trabalho->id)->firstOrFail();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
        $this->assertSame($documento->id, (int) $banca->fresh()->documento_homologacao_id);

        // E editar pela Secretaria também preserva "aguardando".
        $this->actingAs($secretaria)->put(route('bancas.update', $banca), [
            'trabalho_academico_id' => $trabalho->id,
            'status' => 'cadastrada',
        ] + $this->dadosQualificacao($trabalho, local: 'Sala 3'))->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
    }

    public function test_pedido_de_defesa_exige_tipo_de_impacto(): void
    {
        [$discente, $trabalho, $defesa, $documento] = $this->defesaProposta();
        $this->criarItensDaDefesa($trabalho);
        $impacto = $trabalho->impacto;
        $impacto->delete();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors(['homologacao' => 'Preencha o impacto da dissertação em Meu trabalho antes de enviar a defesa à homologação.']);
        $this->assertSame('proposta', $defesa->fresh()->homologacao_status);

        \App\Models\TrabalhoImpacto::create($impacto->only(['trabalho_academico_id', 'impacto_cientifico_aplicavel', 'impacto_cientifico_descricao']));
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $defesa->fresh()->homologacao_status);
    }

    public function test_pedido_de_qualificacao_nao_exige_itens_da_defesa(): void
    {
        Mail::fake();
        [$discente, , $trabalho] = $this->estrutura();
        $banca = $this->proporBanca($discente, $trabalho);
        $documento = $this->documento($trabalho, $discente);
        // As atividades padrão são exigidas só na defesa.
        AtividadeFormativa::criarPadroes($trabalho->programa);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $banca->fresh()->homologacao_status);
        $this->assertDatabaseCount('trabalho_impactos', 0);
    }

    public function test_pedido_exige_atividades_formativas_da_etapa(): void
    {
        [$discente, $trabalho, $defesa, $documento] = $this->defesaProposta();
        $this->criarItensDaDefesa($trabalho);
        $extensao = AtividadeFormativa::where('nome', 'Atividade Extensionista')->sole();
        AtividadeRealizada::where('atividade_formativa_id', $extensao->id)->delete();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors(['homologacao' => 'Faltam atividades formativas para enviar à homologação: Atividade Extensionista (0 de 1).']);
        $this->assertSame('proposta', $defesa->fresh()->homologacao_status);

        AtividadeRealizada::create([
            'trabalho_academico_id' => $trabalho->id, 'atividade_formativa_id' => $extensao->id, 'descricao' => 'Oficina',
        ]);
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $defesa->fresh()->homologacao_status);
    }

    public function test_pedido_de_defesa_exige_producao_confirmada(): void
    {
        [$discente, $trabalho, $defesa, $documento] = $this->defesaProposta();
        $this->criarItensDaDefesa($trabalho);
        $trabalho->update(['producao_confirmada_em' => null]);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors(['homologacao' => 'Confirme a produção associada em Meu trabalho antes de enviar a defesa à homologação.']);
        $this->assertSame('proposta', $defesa->fresh()->homologacao_status);
    }

    public function test_pedido_de_defesa_exige_minimo_de_publicacoes_do_curso(): void
    {
        [$discente, $trabalho, $defesa, $documento] = $this->defesaProposta();
        $this->criarItensDaDefesa($trabalho);
        ProducaoPublicacao::where('trabalho_academico_id', $trabalho->id)->delete();
        $trabalho->update(['producao_confirmada_em' => now()]);

        // Sem curso no vínculo vale o padrão do nível (mestrado: 1).
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors(['homologacao' => 'Para enviar a defesa, o seu curso exige pelo menos 1 publicação na produção associada; você informou 0.']);

        // Curso com mínimo 0 passa sem publicação.
        $curso = ProgramaCurso::create([
            'programa_id' => $trabalho->programa_id, 'nivel' => 'mestrado', 'modalidade' => 'academico',
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'min_publicacoes_defesa' => 0, 'ativo' => true,
        ]);
        $trabalho->discenteVinculo->update(['programa_curso_id' => $curso->id]);
        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $defesa->fresh()->homologacao_status);
    }

    public function test_pedido_de_defesa_com_o_minimo_de_publicacoes_passa(): void
    {
        [$discente, $trabalho, $defesa, $documento] = $this->defesaProposta();
        $this->criarItensDaDefesa($trabalho);
        $curso = ProgramaCurso::create([
            'programa_id' => $trabalho->programa_id, 'nivel' => 'mestrado', 'modalidade' => 'academico',
            'prazo_qualificacao_meses' => 18, 'prazo_defesa_meses' => 24, 'min_publicacoes_defesa' => 1, 'ativo' => true,
        ]);
        $trabalho->discenteVinculo->update(['programa_curso_id' => $curso->id]);

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasNoErrors();
        $this->assertSame('aguardando', $defesa->fresh()->homologacao_status);
    }

    public function test_pedido_de_defesa_exige_informacoes_adicionais(): void
    {
        [$discente, $trabalho, $defesa, $documento] = $this->defesaProposta();
        $this->criarItensDaDefesa($trabalho);
        $trabalho->informacoesAdicionais()->delete();

        $this->actingAs($discente)->post(route('meu-trabalho.enviar-homologacao', $documento))
            ->assertSessionHasErrors(['homologacao' => 'Responda as Informações adicionais em Meu trabalho antes de enviar a defesa à homologação.']);
        $this->assertSame('proposta', $defesa->fresh()->homologacao_status);
    }

    /** Qualificação aprovada, defesa proposta com espelho respondido e dissertação anexada; faltam só os itens da defesa. */
    private function defesaProposta(): array
    {
        [$discente, , $trabalho] = $this->estrutura();
        $qualificacao = $this->proporBanca($discente, $trabalho);
        $qualificacao->update(['status' => 'finalizada', 'resultado' => 'aprovado', 'homologacao_status' => 'homologada']);
        $this->actingAs($discente)->post(route('bancas.store'), ['tipo' => 'defesa'] + $this->dadosQualificacao($trabalho))
            ->assertSessionHasNoErrors();
        $defesa = Banca::where('trabalho_academico_id', $trabalho->id)->where('tipo', 'defesa')->firstOrFail();
        $documento = $this->documento($trabalho, $discente, 'defesa');
        $barema = Barema::create([
            'programa_id' => $trabalho->programa_id, 'nome' => 'Defesa', 'tipo' => 'defesa',
            'nivel' => 'ambos', 'versao' => '1', 'ativo' => true,
        ]);
        EspelhoDiscente::create(['banca_id' => $defesa->id, 'barema_id' => $barema->id, 'nota_final' => 8, 'enviada_em' => now()]);

        return [$discente, $trabalho, $defesa, $documento];
    }

    public function test_rota_enviar_banca_nao_existe_mais(): void
    {
        $this->assertFalse(Route::has('meu-trabalho.enviar-banca'));
        $this->assertTrue(Route::has('meu-trabalho.enviar-homologacao'));
    }

    private function proporBanca(User $discente, TrabalhoAcademico $trabalho): Banca
    {
        $this->actingAs($discente)->post(route('bancas.store'), $this->dadosQualificacao($trabalho))
            ->assertSessionHasNoErrors();

        return Banca::where('trabalho_academico_id', $trabalho->id)->latest('id')->firstOrFail();
    }

    private function documento(TrabalhoAcademico $trabalho, User $discente, string $tipo = 'qualificacao'): TrabalhoDocumento
    {
        return TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'enviado_por_id' => $discente->id,
            'tipo_documento' => $tipo,
            'arquivo_pdf_path' => 'trabalhos/x.pdf',
            'arquivo_pdf_nome_original' => 'x.pdf',
            'enviado_em' => now(),
        ]);
    }

    private function dadosPdf(): array
    {
        return [
            'tipo_documento' => 'qualificacao',
            'titulo' => 'Título do trabalho',
            'resumo' => 'Resumo do trabalho.',
            'palavras_chave' => 'a, b, c',
            'pdf' => UploadedFile::fake()->createWithContent('trabalho.pdf', file_get_contents(base_path('docs/dissertacao.pdf'))),
        ];
    }

    private function dadosQualificacao(TrabalhoAcademico $trabalho, string $local = 'Sala 1'): array
    {
        $avaliadores = DocenteProgramaVinculo::where('programa_id', $trabalho->programa_id)
            ->where('pode_avaliar', true)
            ->get();

        return [
            'tipo' => 'qualificacao',
            'local' => $local,
            'quantidade_avaliadores' => 2,
            'avaliador_interno_id' => $avaliadores->firstWhere('tipo_vinculo', 'interno')->id,
            'avaliador_externo_id' => $avaliadores->firstWhere('tipo_vinculo', 'externo')->id,
        ];
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

        $this->criarDadosPesquisa($trabalho);

        return [$discenteUser, $secretaria, $trabalho->fresh()];
    }
}
