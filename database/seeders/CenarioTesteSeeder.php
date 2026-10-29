<?php

namespace Database\Seeders;

use App\Models\AreaConcentracao;
use App\Models\AreaCnpq;
use App\Models\SetorCnae;
use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\LinhaPesquisa;
use App\Models\OrcidWork;
use App\Models\Orientacao;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\TrabalhoAcademico;
use App\Models\User;
use App\Services\ContextoSecretaria;
use App\Services\CursoProgramaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuários de teste de todos os perfis, sobre o programa PPGMSB já cadastrado.
 * Não altera os dados do programa, da instituição, das áreas nem das linhas.
 * Senha de todos: uneb@123. Idempotente.
 *
 *   php artisan db:seed --class=CenarioTesteSeeder
 */
class CenarioTesteSeeder extends Seeder
{
    private const SENHA = 'uneb@123';

    public function run(): void
    {
        $programa = Programa::where('sigla', 'PPGMSB')->firstOrFail();
        $curso = $this->curso($programa);
        $linha = $this->linha($programa);
        $contexto = app(ContextoSecretaria::class);

        // Secretaria — segunda servidora do mesmo programa
        $contexto->vincular($this->usuario('Secretaria Dois', 'secretaria2@ppgmsb.test', 'sec'), $programa);

        // Docentes
        $permanente1 = $this->docente($programa, 'Beatriz Carvalho Mendes', 'permanente1@ppgmsb.test', 'UNEB', 'Universidade do Estado da Bahia', 'interno', 'permanente', true);
        $permanente2 = $this->docente($programa, 'Rafael Teixeira Nunes', 'permanente2@ppgmsb.test', 'UNEB', 'Universidade do Estado da Bahia', 'interno', 'permanente', true);
        $colaborador = $this->docente($programa, 'Luciana Prado Freitas', 'colaborador@ppgmsb.test', 'UNEB', 'Universidade do Estado da Bahia', 'interno', 'colaborador', true);
        $externoUfba = $this->docente($programa, 'Eduardo Moura Bastos', 'externo.ufba@ppgmsb.test', 'UFBA', 'Universidade Federal da Bahia', 'externo', null, false);
        $this->docente($programa, 'Profa. Externa USP', 'externo.usp@ppgmsb.test', 'USP', 'Universidade de São Paulo', 'externo', null, false);

        // Discentes
        $completo = $this->discente($programa, $curso, $linha, 'Juliana Ribeiro Santos', 'discente.completo@ppgmsb.test', 'TESTE-001', 'https://orcid.org/0000-0002-1825-0097');
        $this->orientacao($completo, $permanente1, 'orientador');
        $this->orientacao($completo, $permanente2, 'coorientador');
        $this->configurarDissertacaoTeste($completo, $permanente1, $colaborador, $externoUfba);

        $semOrcid = $this->discente($programa, $curso, $linha, 'Discente Sem ORCID', 'discente.sem.orcid@ppgmsb.test', 'TESTE-002', null);
        $this->orientacao($semOrcid, $permanente2, 'orientador');

        $this->discente($programa, $curso, $linha, 'Discente Sem Orientador', 'discente.sem.orientador@ppgmsb.test', 'TESTE-003', 'https://orcid.org/0000-0001-5109-3700');

        // Troca registrada: aparece na linha do tempo da edição do discente.
        $comTroca = $this->discente($programa, $curso, $linha, 'Discente Com Troca', 'discente.com.troca@ppgmsb.test', 'TESTE-004', 'https://orcid.org/0000-0002-9079-593X');
        $this->orientacao($comTroca, $permanente1, 'orientador', inicio: now()->subMonths(10), fim: now()->subMonths(2));
        $this->orientacao($comTroca, $permanente2, 'orientador', inicio: now()->subMonths(2), extra: [
            'processo_sei' => '0601.2026/000123-4',
            'justificativa' => 'Afastamento da orientadora para pós-doutorado (exemplo fictício).',
        ]);

        $this->baremasDeTeste($programa);
        $this->baremaAutoavaliacaoDeTeste($programa);

        // Outro programa — para testar o isolamento entre Secretarias
        $instituicaoOutra = Instituicao::firstOrCreate(
            ['sigla' => 'UFBA'],
            ['nome' => 'Universidade Federal da Bahia', 'cidade' => 'Salvador', 'estado' => 'BA', 'ativo' => true],
        );
        $outro = Programa::firstOrCreate(
            ['sigla' => 'PPGTI'],
            ['nome' => 'Programa de Pós-Graduação em Teste de Isolamento', 'instituicao_id' => $instituicaoOutra->id, 'ativo' => true],
        );
        $contexto->vincular($this->usuario('Secretaria Outro Programa', 'secretaria.outro@ppgmsb.test', 'sec'), $outro);
        $this->discente($outro, $this->curso($outro), $this->linha($outro), 'Discente Outro Programa', 'discente.outro@ppgmsb.test', 'OUTRO-001', 'https://orcid.org/0000-0003-1415-9265');
    }

    private function curso(Programa $programa): ProgramaCurso
    {
        return $programa->cursos()->where('nivel', 'mestrado')->where('modalidade', 'academico')->first()
            ?? app(CursoProgramaService::class)->criar($programa, 'mestrado', 'academico');
    }

    private function linha(Programa $programa): LinhaPesquisa
    {
        $existente = LinhaPesquisa::where('ativo', true)
            ->whereHas('areaConcentracao', fn ($q) => $q->where('programa_id', $programa->id))
            ->orderBy('id')
            ->first();
        if ($existente) {
            return $existente;
        }

        $area = AreaConcentracao::firstOrCreate(
            ['programa_id' => $programa->id, 'nome' => 'Modelagem Aplicada a Biossistemas'],
            ['ativo' => true],
        );

        return LinhaPesquisa::firstOrCreate(
            ['area_concentracao_id' => $area->id, 'nome' => 'Modelagem e Otimização de Biossistemas'],
            ['ativo' => true],
        );
    }

    private function usuario(string $nome, string $email, string $categoria): User
    {
        $usuario = User::updateOrCreate(['email' => $email], [
            'name' => $nome,
            'categoria' => $categoria,
            'password' => Hash::make(self::SENHA),
        ]);

        $usuario->forceFill([
            'password' => Hash::make(self::SENHA),
            'email_verified_at' => now(),
            'primeiro_acesso' => false,
            'cadastro_status' => 'completo',
            'ativo' => true,
        ])->save();

        return $usuario;
    }

    private function docente(
        Programa $programa,
        string $nome,
        string $email,
        string $sigla,
        string $instituicao,
        string $tipoVinculo,
        ?string $categoriaDocente,
        bool $podeOrientar,
    ): DocenteProgramaVinculo {
        $usuario = $this->usuario($nome, $email, 'docente');
        $numero = str_pad((string) $usuario->id, 4, '0', STR_PAD_LEFT);

        $profile = DocenteProfile::updateOrCreate(['user_id' => $usuario->id], [
            'titulacao' => 'Doutorado',
            'area_formacao' => 'Modelagem Computacional',
            'lattes_url' => 'http://lattes.cnpq.br/900000000000' . $numero,
            'orcid_url' => 'https://orcid.org/0000-0002-9000-' . $numero,
            'instituicao_origem' => $instituicao,
            'instituicao_sigla' => $sigla,
            'ativo' => true,
        ]);

        return DocenteProgramaVinculo::updateOrCreate(
            ['programa_id' => $programa->id, 'docente_profile_id' => $profile->id],
            [
                'tipo_vinculo' => $tipoVinculo,
                'categoria_docente' => $categoriaDocente,
                'pode_orientar' => $podeOrientar,
                'pode_avaliar' => true,
                'ativo' => true,
            ],
        );
    }

    private function discente(
        Programa $programa,
        ProgramaCurso $curso,
        LinhaPesquisa $linha,
        string $nome,
        string $email,
        string $matricula,
        ?string $orcid,
    ): DiscenteProgramaVinculo {
        $usuario = $this->usuario($nome, $email, 'discente');

        $profile = DiscenteProfile::updateOrCreate(['user_id' => $usuario->id], [
            'lattes_url' => 'http://lattes.cnpq.br/800000000000' . str_pad((string) $usuario->id, 4, '0', STR_PAD_LEFT),
            'orcid_url' => $orcid,
            'pais' => 'Brasil',
            'ativo' => true,
        ]);

        $vinculo = DiscenteProgramaVinculo::updateOrCreate(
            ['programa_id' => $programa->id, 'discente_profile_id' => $profile->id],
            [
                'programa_curso_id' => $curso->id,
                'linha_pesquisa_id' => $linha->id,
                'matricula' => $matricula,
                'nivel' => $curso->nivel,
                'status' => 'ativo',
                'data_ingresso' => now()->subMonths(12)->toDateString(),
                'ativo' => true,
            ],
        );

        TrabalhoAcademico::updateOrCreate(['discente_programa_vinculo_id' => $vinculo->id], [
            'programa_id' => $programa->id,
            'linha_pesquisa_id' => $linha->id,
            'nivel' => $vinculo->nivel,
            'status' => 'em_andamento',
            'data_inicio' => $vinculo->data_ingresso,
        ]);

        return $vinculo;
    }

    private function orientacao(
        DiscenteProgramaVinculo $discente,
        DocenteProgramaVinculo $docente,
        string $tipo,
        ?\DateTimeInterface $inicio = null,
        ?\DateTimeInterface $fim = null,
        array $extra = [],
    ): void {
        Orientacao::updateOrCreate(
            [
                'trabalho_academico_id' => $discente->trabalhoAcademico->id,
                'docente_programa_vinculo_id' => $docente->id,
            ],
            [
                'discente_programa_vinculo_id' => $discente->id,
                'tipo' => $tipo,
                'ordem' => 1,
                'ativo' => $fim === null,
                'inicio_em' => ($inicio ?? $discente->data_ingresso ?? now())->format('Y-m-d'),
                'fim_em' => $fim?->format('Y-m-d'),
                ...$extra,
            ],
        );
    }

    /** Baremas curtos (3 perguntas) para testar a avaliação: qualificação, defesa e as versões confidenciais. */
    private function baremasDeTeste(Programa $programa): void
    {
        $baremas = [
            'qualificacao' => ['Barema de Qualificação (teste)', [
                'Clareza do problema e dos objetivos',
                'Adequação da metodologia proposta',
                'Viabilidade do cronograma',
            ]],
            'defesa' => ['Barema de Defesa (teste)', [
                'Relevância e originalidade dos resultados',
                'Rigor metodológico e análise',
                'Qualidade da redação e da apresentação',
            ]],
            'qualificacao_confidencial' => ['Barema Confidencial de Qualificação (teste)', [
                'Maturidade do discente para seguir à defesa',
                'Autonomia do discente na pesquisa',
                'Desempenho na arguição',
            ]],
            'defesa_confidencial' => ['Barema Confidencial de Defesa (teste)', [
                'Domínio do tema demonstrado na arguição',
                'Contribuição do trabalho para a área',
                'Recomendação de publicação dos resultados',
            ]],
        ];

        foreach ($baremas as $tipo => [$nome, $perguntas]) {
            $barema = Barema::updateOrCreate(
                ['programa_id' => $programa->id, 'tipo' => $tipo, 'versao' => 'teste'],
                ['nome' => $nome, 'nivel' => 'ambos', 'ativo' => true, 'descricao' => 'Barema de teste com 3 perguntas.'],
            );

            $secao = BaremaSecao::updateOrCreate(
                ['barema_id' => $barema->id, 'ordem' => 1],
                ['nome' => 'Avaliação', 'peso' => 1, 'ativo' => true],
            );

            foreach ($perguntas as $i => $pergunta) {
                BaremaItem::updateOrCreate(
                    ['barema_secao_id' => $secao->id, 'ordem' => $i + 1],
                    ['nome' => $pergunta, 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ativo' => true],
                );
            }
        }
    }

    /** Autoavaliação do orientador (preenchida só na defesa): 5 dimensões, 3 afirmações cada, nota 0–10. */
    private function baremaAutoavaliacaoDeTeste(Programa $programa): void
    {
        $dimensoes = [
            'Planejamento e acompanhamento' => [
                'Estabeleci, em conjunto com o orientando, objetivos, expectativas e um cronograma de trabalho claros e factíveis.',
                'Acompanhei regularmente o desenvolvimento da pesquisa, oferecendo orientações e devolutivas adequadas.',
                'Identifiquei dificuldades durante o processo de orientação e contribuí para a definição de soluções.',
            ],
            'Disponibilidade e comunicação' => [
                'Mantive disponibilidade adequada para reuniões, esclarecimento de dúvidas e acompanhamento do orientando.',
                'Estabeleci uma comunicação clara, respeitosa e tempestiva durante o processo de orientação.',
                'Proporcionei um ambiente favorável ao diálogo, à apresentação de ideias e à manifestação de dúvidas e divergências.',
            ],
            'Formação científica e acadêmica' => [
                'Contribuí para o aprofundamento teórico e o aprimoramento metodológico da pesquisa desenvolvida pelo orientando.',
                'Estimulei a autonomia intelectual, o pensamento crítico e a capacidade de investigação científica do orientando.',
                'Incentivei a produção científica, a divulgação dos resultados e o desenvolvimento de uma postura ética e profissional.',
            ],
            'Relação orientador-orientando' => [
                'Considerei as necessidades e particularidades do orientando, respeitando seu ritmo de desenvolvimento acadêmico.',
                'Mantive uma relação de orientação fundamentada no respeito mútuo, na confiança e na colaboração.',
                'Conduzi cobranças, dificuldades e eventuais divergências de maneira equilibrada e construtiva.',
            ],
            'Resultados e aperfeiçoamento da orientação' => [
                'Minha atuação contribuiu efetivamente para a evolução acadêmica e científica do orientando.',
                'Contribuí para que o desenvolvimento da pesquisa permanecesse coerente com os objetivos e prazos estabelecidos.',
                'Avalio criticamente minha prática de orientação e busco aperfeiçoar meus procedimentos de acompanhamento e formação científica.',
            ],
        ];

        $barema = Barema::updateOrCreate(
            ['programa_id' => $programa->id, 'tipo' => 'autoavaliacao_orientador', 'versao' => 'teste'],
            ['nome' => 'Autoavaliação do orientador (teste)', 'nivel' => 'ambos', 'ativo' => true, 'descricao' => 'Autoavaliação do orientador na banca de defesa.'],
        );

        $ordemSecao = 0;
        foreach ($dimensoes as $dimensao => $afirmacoes) {
            $secao = BaremaSecao::updateOrCreate(
                ['barema_id' => $barema->id, 'ordem' => ++$ordemSecao],
                ['nome' => $dimensao, 'peso' => 1, 'ativo' => true],
            );

            foreach ($afirmacoes as $i => $afirmacao) {
                BaremaItem::updateOrCreate(
                    ['barema_secao_id' => $secao->id, 'ordem' => $i + 1],
                    ['nome' => $afirmacao, 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ativo' => true],
                );
            }
        }
    }

    /**
     * Sincroniza os metadados do trabalho e a banca de defesa da discente Juliana Ribeiro Santos
     * com os dados fictícios de dissertacao/dissertacao.tex.
     */
    private function configurarDissertacaoTeste(
        DiscenteProgramaVinculo $discente,
        DocenteProgramaVinculo $orientador,
        DocenteProgramaVinculo $membroInterno,
        DocenteProgramaVinculo $membroExterno,
    ): void {
        $trabalho = $discente->trabalhoAcademico;
        if (! $trabalho) {
            return;
        }

        // Produção registrada no ORCID da orientadora para conferência de referências da dissertação de teste
        $perfilOrientador = $orientador->docenteProfile;
        if ($perfilOrientador) {
            foreach ([
                [
                    'doi' => '10.1016/j.annepidem.2025.06.012',
                    'titulo' => 'Integrated statistical modeling and machine learning techniques with SHAP for epidemiological data analysis',
                    'ano' => 2025,
                    'journal' => 'Annals of Epidemiology',
                ],
                [
                    'doi' => '10.3934/math.20251006',
                    'titulo' => 'Intelligent forecasting of monkeypox spread using fractional epidemiological models and machine learning',
                    'ano' => 2025,
                    'journal' => 'AIMS Mathematics',
                ],
            ] as $obra) {
                OrcidWork::updateOrCreate(
                    ['docente_profile_id' => $perfilOrientador->id, 'doi' => $obra['doi']],
                    [
                        'orcid_id' => '0000-0002-9000-'.str_pad((string) $perfilOrientador->user_id, 4, '0', STR_PAD_LEFT),
                        'titulo' => $obra['titulo'],
                        'ano' => $obra['ano'],
                        'tipo' => 'journal-article',
                        'journal' => $obra['journal'],
                        'fonte' => 'orcid',
                        'consultado_em' => now(),
                    ],
                );
            }
        }

        $trabalho->update([
            'titulo' => 'Modelagem Computacional e Simulação Estocástica de Biossistemas Aplicadas à Vigilância Epidemiológica',
            'resumo' => 'A modelagem computacional e a simulação estocástica de biossistemas desempenham papel fundamental na compreensão de fenômenos epidemiológicos complexos e no suporte à tomada de decisão em saúde pública. Neste trabalho fictício de teste para o sistema de gestão de bancas do PPGMSB, propomos uma arquitetura híbrida que integra equações diferenciais estocásticas e algoritmos de aprendizado de máquina para a predição espaço-temporal de surtos epidemiológicos em cenários com dados ruidosos. O método compreende três etapas principais: pré-processamento e filtragem adaptativa de séries temporais, calibração paramétrica via inferência bayesiana e simulação de cenários de intervenção. Os experimentos numéricos sintéticos demonstram estabilidade numérica, redução do erro quadrático médio de predição e elevada aderência aos critérios de reprodutibilidade científica do programa.',
            'palavras_chave' => 'modelagem computacional; simulação estocástica; biossistemas; aprendizado de máquina; vigilância epidemiológica',
        ]);

        // Dados para o Lattes (listas oficiais de áreas CNPq e setores CNAE)
        $this->call(TabelasLattesSeeder::class);
        $trabalho->update([
            'titulo_en' => 'Computational Modeling and Stochastic Simulation of Biosystems Applied to Epidemiological Surveillance',
            'outras_informacoes' => 'Trabalho fictício de teste; bolsa CAPES (exemplo).',
            'outras_informacoes_en' => 'Fictitious test work; CAPES grant (example).',
            'visibilidade' => 'publica',
            'setor_cnae_id' => SetorCnae::where('codigo', '86')->value('id'),
        ]);
        $trabalho->areasCnpq()->sync(AreaCnpq::whereIn('codigo', ['40601005', '10104003'])->pluck('id'));
        $trabalho->programa?->update(['homepage_trabalhos' => 'https://ppgmsb.uneb.br/trabalhos']);

        // Qualificação já aprovada para permitir banca de defesa
        $qualificacao = Banca::updateOrCreate(
            ['trabalho_academico_id' => $trabalho->id, 'tipo' => 'qualificacao'],
            [
                'status' => 'finalizada',
                'homologacao_status' => 'homologada',
                'resultado' => 'aprovado',
                'nota_media' => 9.5,
                'data_hora' => '2026-05-15 14:00:00',
                'local' => 'Alagoinhas/BA',
            ],
        );

        foreach ([[1, $membroInterno], [2, $membroExterno]] as [$ordem, $docente]) {
            BancaMembro::updateOrCreate(
                ['banca_id' => $qualificacao->id, 'docente_programa_vinculo_id' => $docente->id],
                ['papel' => 'avaliador', 'ordem' => $ordem, 'ativo' => true],
            );
        }

        // Banca de defesa proposta, sincronizada com dissertacao/dissertacao.tex
        $defesa = Banca::updateOrCreate(
            ['trabalho_academico_id' => $trabalho->id, 'tipo' => 'defesa'],
            [
                'status' => 'cadastrada',
                'homologacao_status' => 'proposta',
                'data_hora' => '2026-11-30 14:00:00',
                'local' => 'Alagoinhas/BA',
            ],
        );

        foreach ([[1, $membroInterno], [2, $membroExterno]] as [$ordem, $docente]) {
            BancaMembro::updateOrCreate(
                ['banca_id' => $defesa->id, 'docente_programa_vinculo_id' => $docente->id],
                ['papel' => 'avaliador', 'ordem' => $ordem, 'ativo' => true],
            );
        }
    }
}
