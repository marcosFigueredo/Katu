<?php

namespace Database\Seeders;

use App\Models\AreaConcentracao;
use App\Models\Barema;
use App\Models\BaremaItem;
use App\Models\BaremaSecao;
use App\Models\DiscenteProfile;
use App\Models\DiscenteProgramaVinculo;
use App\Models\DocenteProfile;
use App\Models\DocenteProgramaVinculo;
use App\Models\Instituicao;
use App\Models\LinhaPesquisa;
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
        $permanente1 = $this->docente($programa, 'Profa. Permanente Um', 'permanente1@ppgmsb.test', 'UNEB', 'Universidade do Estado da Bahia', 'interno', 'permanente', true);
        $permanente2 = $this->docente($programa, 'Prof. Permanente Dois', 'permanente2@ppgmsb.test', 'UNEB', 'Universidade do Estado da Bahia', 'interno', 'permanente', true);
        $this->docente($programa, 'Profa. Colaboradora', 'colaborador@ppgmsb.test', 'UNEB', 'Universidade do Estado da Bahia', 'interno', 'colaborador', true);
        $this->docente($programa, 'Prof. Externo UFBA', 'externo.ufba@ppgmsb.test', 'UFBA', 'Universidade Federal da Bahia', 'externo', null, false);
        $this->docente($programa, 'Profa. Externa USP', 'externo.usp@ppgmsb.test', 'USP', 'Universidade de São Paulo', 'externo', null, false);

        // Discentes
        $completo = $this->discente($programa, $curso, $linha, 'Discente Completo', 'discente.completo@ppgmsb.test', 'TESTE-001', 'https://orcid.org/0000-0002-1825-0097');
        $this->orientacao($completo, $permanente1, 'orientador');
        $this->orientacao($completo, $permanente2, 'coorientador');

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
            ['programa_id' => $programa->id, 'nome' => 'Área de Teste'],
            ['ativo' => true],
        );

        return LinhaPesquisa::firstOrCreate(
            ['area_concentracao_id' => $area->id, 'nome' => 'Linha de Teste'],
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
}
