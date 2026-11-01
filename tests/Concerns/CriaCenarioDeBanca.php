<?php

namespace Tests\Concerns;

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
use App\Models\TrabalhoDocumento;
use App\Models\TrabalhoSubmissaoLatex;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Cenário completo de banca aguardando homologação (usa CriaBaremasAtivos). */
trait CriaCenarioDeBanca
{
    use CriaBaremasAtivos;

    /**
     * Banca com discente (ORCID), orientador, coorientador, submissão LaTeX ok, documento e 2 avaliadores.
     * Com $comBaremas, o programa já tem todos os baremas ativos exigidos.
     *
     * @return array{0: User, 1: Programa, 2: Banca, 3: User, 4: User, 5: User, 6: TrabalhoSubmissaoLatex, 7: list<User>}
     */
    protected function criarCenarioCompleto(string $tipo = 'qualificacao', bool $comBaremas = true): array
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
            'tipo' => $tipo,
            'status' => 'cadastrada',
            'homologacao_status' => 'aguardando',
            'homologacao_tentativa' => 1,
            'libera_envio_trabalho' => false,
            'data_hora' => now()->addDays(15),
            'local' => 'Auditório PPGMSB',
        ]);

        if ($comBaremas) {
            $this->criarBaremasAtivos($programa);
        }

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

        if ($banca->tipo === 'defesa') {
            // Defesa exige o mínimo de publicações do curso (mestrado: 1), conferido pela Secretaria.
            $trabalho->publicacoes()->create([
                'tipo' => 'artigo', 'titulo' => 'Artigo derivado da dissertação', 'ano' => 2025, 'veiculo' => 'Revista X',
                'situacao' => 'publicada', 'conta_minimo' => true, 'conferida_em' => now(),
            ]);

            // E as atividades formativas padrão, cumpridas e confirmadas.
            \App\Models\AtividadeFormativa::criarPadroes($programa);
            foreach (\App\Models\AtividadeFormativa::where('programa_id', $programa->id)->get() as $atividade) {
                $trabalho->atividadesRealizadas()->create([
                    'atividade_formativa_id' => $atividade->id, 'descricao' => "Cumprida: {$atividade->nome}",
                    'conferencia' => 'confirmada', 'conferida_em' => now(),
                ]);
            }
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
