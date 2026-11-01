<?php

namespace Database\Seeders;

use App\Models\AreaConcentracao;
use App\Models\AtividadeFormativa;
use App\Models\TrabalhoImpacto;
use App\Models\TrabalhoInformacoesAdicionais;
use App\Models\AreaCnpq;
use App\Models\SetorCnae;
use App\Models\Banca;
use App\Models\BancaMembro;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\DimensaoQualidade;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\LinhaPesquisa;
use App\Models\OrcidWork;
use App\Models\Bolsa;
use App\Models\MembroCpa;
use App\Models\PeriodoQualidade;
use App\Models\Orientacao;
use App\Models\ProjetoPesquisa;
use App\Models\TrabalhoProjetoVinculo;
use App\Models\Programa;
use App\Models\ProgramaCurso;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDadosPesquisa;
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

    /** Dimensão de cada pergunta dos baremas de teste (escolha pelo conteúdo; a CPA ajusta nos baremas reais). */
    private const DIMENSAO_DAS_PERGUNTAS = [
        'Clareza do problema e dos objetivos' => 'rigor',
        'Adequação da metodologia proposta' => 'rigor',
        'Viabilidade do cronograma' => 'rigor',
        'Relevância e originalidade dos resultados' => 'originalidade',
        'Rigor metodológico e análise' => 'rigor',
        'Qualidade da redação e da apresentação' => 'rigor',
        'Maturidade do discente para seguir à defesa' => 'autonomia',
        'Autonomia do discente na pesquisa' => 'autonomia',
        'Desempenho na arguição' => 'autonomia',
        'Domínio do tema demonstrado na arguição' => 'autonomia',
        'Contribuição do trabalho para a área' => 'relevancia',
        'Recomendação de publicação dos resultados' => 'producao',
    ];

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

        $this->projetosEBolsas($permanente1, $permanente2, $completo, $semOrcid, $comTroca);

        // CPA: Luciana com mandato vigente e o quadriênio de avaliação (faixas padrão, pesos iguais até a CPA usar o SWARA).
        MembroCpa::updateOrCreate(
            ['programa_id' => $programa->id, 'user_id' => $colaborador->docenteProfile->user_id],
            ['inicio_em' => now()->subMonths(3)->toDateString(), 'fim_em' => null, 'numero_ato' => 'Portaria nº 45/2026 (exemplo)'],
        );
        PeriodoQualidade::updateOrCreate(
            ['programa_id' => $programa->id, 'nome' => 'Quadriênio 2025–2028'],
            ['inicio_em' => '2025-01-01', 'fim_em' => '2028-12-31'],
        );

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

    /**
     * Projetos de pesquisa dos permanentes (Beatriz com um concluído e um ativo; Rafael com um ativo; a colaboradora sem),
     * vínculos dos orientandos (Juliana com objetivo; Sem ORCID pendente; Com Troca precisa dizer se mantém) e a bolsa da Juliana.
     */
    private function projetosEBolsas(
        DocenteProgramaVinculo $beatriz,
        DocenteProgramaVinculo $rafael,
        DiscenteProgramaVinculo $juliana,
        DiscenteProgramaVinculo $semOrcid,
        DiscenteProgramaVinculo $comTroca,
    ): void {
        $projeto = fn (DocenteProgramaVinculo $docente, string $titulo, string $descricao, string $inicio, ?string $fim, string $situacao) => ProjetoPesquisa::updateOrCreate(
            ['docente_programa_vinculo_id' => $docente->id, 'titulo' => $titulo],
            ['descricao' => $descricao, 'inicio_em' => $inicio, 'fim_em' => $fim, 'situacao' => $situacao, 'registrado_por' => $docente->docenteProfile?->user_id],
        );

        $antigoBeatriz = $projeto($beatriz, 'Vigilância epidemiológica de arboviroses na Bahia',
            "Objetivo 1: mapear a incidência de dengue por município.
Objetivo 2: avaliar a cobertura da notificação.",
            '2021-03-01', now()->subMonths(6)->toDateString(), 'concluido');
        $ativoBeatriz = $projeto($beatriz, 'Modelagem matemática de arboviroses e mudanças climáticas',
            "Objetivo 1: estimar o número reprodutivo básico (R0) sob cenários climáticos.
Objetivo 2: calibrar modelos compartimentais com dados de vigilância.
Objetivo 3: apoiar a gestão com cenários de intervenção.",
            now()->subMonths(6)->toDateString(), null, 'ativo');
        $ativoRafael = $projeto($rafael, 'Ciência de dados aplicada à atenção primária',
            "Objetivo 1: prever internações por condições sensíveis à atenção primária.
Objetivo 2: avaliar a equidade no acesso.",
            '2024-02-01', null, 'ativo');

        $vincular = fn (DiscenteProgramaVinculo $discente, ProjetoPesquisa $p, string $motivo, bool $ativo, ?string $objetivo, ?bool $mantem = null) => TrabalhoProjetoVinculo::updateOrCreate(
            ['trabalho_academico_id' => $discente->trabalhoAcademico->id, 'projeto_pesquisa_id' => $p->id],
            [
                'motivo' => $motivo, 'inicio_em' => $p->inicio_em, 'ativo' => $ativo, 'fim_em' => $ativo ? null : now()->subMonths(2)->toDateString(),
                'objetivo' => $objetivo, 'mantem_objetivo' => $mantem, 'objetivo_informado_em' => $objetivo ? now()->subMonth() : null,
            ],
        );

        $vincular($juliana, $antigoBeatriz, 'orientador', false, 'Objetivo 2: avaliar a cobertura da notificação.');
        $vincular($juliana, $ativoBeatriz, 'novo_projeto', true, 'Objetivo 2: calibrar modelos compartimentais com dados de vigilância.', false);
        $vincular($semOrcid, $ativoRafael, 'orientador', true, null);
        $vincular($comTroca, $ativoBeatriz, 'orientador', false, 'Objetivo 1: estimar o R0 sob cenários climáticos.');
        $vincular($comTroca, $ativoRafael, 'troca_orientador', true, null);

        Bolsa::updateOrCreate(
            ['discente_programa_vinculo_id' => $juliana->id, 'agencia' => 'capes'],
            ['modalidade' => 'mestrado', 'numero_processo' => '88887.000000/2025-00', 'inicio_em' => now()->subMonths(11)->toDateString(), 'fim_em' => now()->addMonths(13)->toDateString()],
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
                    ['nome' => $pergunta, 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ativo' => true,
                        'dimensao_qualidade_id' => DimensaoQualidade::doCodigo(self::DIMENSAO_DAS_PERGUNTAS[$pergunta] ?? 'rigor')->id],
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
                    ['nome' => $afirmacao, 'nota_maxima' => 10, 'peso' => 1, 'obrigatorio' => true, 'ativo' => true,
                        'dimensao_qualidade_id' => DimensaoQualidade::doCodigo('processo')->id],
                );
            }
        }
    }

    /** Impacto, atividades formativas, produção associada e Informações adicionais da Juliana (pedido de defesa). */
    private function itensDaDefesa(TrabalhoAcademico $trabalho): void
    {
        TrabalhoImpacto::updateOrCreate(['trabalho_academico_id' => $trabalho->id], [
            'impacto_cientifico_aplicavel' => true,
            'impacto_cientifico_descricao' => 'Arquitetura híbrida de modelagem estocástica e aprendizado de máquina para vigilância epidemiológica.',
            'impacto_social_aplicavel' => true,
            'impacto_social_descricao' => 'Apoio às equipes de vigilância do SUS na antecipação de surtos.',
        ]);

        AtividadeFormativa::criarPadroes($trabalho->programa);
        foreach ([
            'Publicação de Produção Científica' => 'Artigo publicado no Annals of Epidemiology (2025).',
            'Atividade Extensionista' => 'Oficina de modelagem epidemiológica para a rede estadual de ensino.',
            'Popularização das Ciências' => 'Podcast "Epidemias e modelos no rádio" (Rádio UNEB, 2025).',
        ] as $nome => $descricao) {
            $atividade = AtividadeFormativa::where('programa_id', $trabalho->programa_id)->where('nome', $nome)->first();
            if ($atividade) {
                $trabalho->atividadesRealizadas()->updateOrCreate(
                    ['atividade_formativa_id' => $atividade->id],
                    ['descricao' => $descricao],
                );
            }
        }

        $trabalho->publicacoes()->updateOrCreate(['doi' => '10.1016/j.annepidem.2025.06.012'], [
            'tipo' => 'artigo',
            'titulo' => 'Integrated statistical modeling and machine learning techniques with SHAP for epidemiological data analysis',
            'ano' => 2025,
            'veiculo' => 'Annals of Epidemiology',
            'issn' => '1047-2797',
        ]);
        $trabalho->extensoes()->updateOrCreate(['titulo' => 'Oficina de modelagem epidemiológica'], [
            'tipo' => 'oficina', 'publico' => 40, 'parceiro' => 'Colégio Estadual de Alagoinhas', 'carga_horaria' => 8,
            'inicio' => '2025-05-10', 'fim' => '2025-05-11', 'registro_proex' => 'PROEX 012/2025',
        ]);
        $trabalho->popularizacoes()->updateOrCreate(['titulo' => 'Epidemias e modelos no rádio'], [
            'tipo' => 'podcast', 'veiculo' => 'Rádio UNEB', 'alcance' => 1200, 'data' => '2025-08-01',
        ]);
        $trabalho->update(['producao_confirmada_em' => now()]);

        TrabalhoInformacoesAdicionais::updateOrCreate(['trabalho_academico_id' => $trabalho->id], [
            'conflito_interesses' => false,
            'disponibilidade_dados' => 'repositorio_publico',
            'dados_repositorio' => 'https://doi.org/10.5281/zenodo.0000000',
            'amostras_fisicas' => false,
            'aprovacao_etica' => false,
            'participantes' => false,
            'uso_ia' => true,
            'ia_ferramentas' => 'Claude',
            'ia_etapas' => 'Revisão de texto da introdução e do resumo.',
            'originalidade_confirmada' => true,
            'preenchido_em' => now(),
        ]);
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

        // Dados da pesquisa (código, dados e ética): exigidos para enviar o pedido à homologação
        TrabalhoDadosPesquisa::updateOrCreate(['trabalho_academico_id' => $trabalho->id], [
            'codigo' => 'publico',
            'codigo_link' => 'https://github.com/ppgmsb/modelo-vigilancia-teste',
            'codigo_justificativa' => null,
            'dados_tipos' => ['secundarios_publicos', 'simulados'],
            'dados_disponibilidade' => 'publico',
            'dados_link' => 'https://doi.org/10.5281/zenodo.0000000',
            'dados_justificativa' => null,
            'seres_humanos' => false,
            'caae' => null,
            'parecer_numero' => null,
            'tcle' => null,
            'tcle_justificativa' => null,
            'animais' => false,
            'ceua_protocolo' => null,
            'preenchido_em' => now(),
        ]);

        $this->itensDaDefesa($trabalho);

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

        // Recomendações da qualificação, para a Juliana responder antes de enviar a defesa
        foreach ([
            [$membroInterno, 'Ampliar a revisão de literatura sobre modelos estocásticos aplicados à vigilância.'],
            [$membroExterno, 'Justificar a escolha dos parâmetros de calibração bayesiana.'],
        ] as [$docente, $texto]) {
            $membro = BancaMembro::where('banca_id', $qualificacao->id)->where('docente_programa_vinculo_id', $docente->id)->first();
            if ($membro) {
                \App\Models\BancaRecomendacao::updateOrCreate(
                    ['banca_id' => $qualificacao->id, 'banca_membro_id' => $membro->id, 'ordem' => 1],
                    ['texto' => $texto],
                );
            }
        }

        // Coordenação e portaria (certificado de participação na banca)
        $programa = $trabalho->programa;
        if ($programa) {
            $programa->update([
                'coordenador_id' => $programa->coordenador_id ?? $orientador->docenteProfile?->user_id,
                'portaria_coordenador' => $programa->portaria_coordenador
                    ?? 'Portaria nº 123/2025, publicada no Diário Oficial do Estado da Bahia em 10/03/2025 (exemplo)',
            ]);
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
