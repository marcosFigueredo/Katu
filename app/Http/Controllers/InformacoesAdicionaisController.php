<?php

namespace App\Http\Controllers;

use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoInformacoesAdicionais;
use App\Services\PedidoHomologacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Informações adicionais da submissão da defesa; exigidas para enviar a defesa à homologação. */
class InformacoesAdicionaisController extends Controller
{
    private const PASTA = 'informacoes-adicionais';

    public function __construct(
        private readonly PedidoHomologacaoService $pedidoHomologacao,
    ) {}

    public function edit()
    {
        $trabalho = $this->trabalho();
        $info = $trabalho->informacoesAdicionais ?? $this->preMarcada($trabalho);
        $travado = $this->pedidoHomologacao->defesaEnviada($trabalho);

        return view('meu-trabalho.informacoes-adicionais', compact('trabalho', 'info', 'travado'));
    }

    public function update(Request $request)
    {
        $trabalho = $this->trabalho();
        if ($this->pedidoHomologacao->defesaEnviada($trabalho)) {
            throw ValidationException::withMessages(['travado' => TrabalhoImpactoController::MENSAGEM_TRAVADO]);
        }

        $atual = $trabalho->informacoesAdicionais;
        $pdf = ['file', 'mimes:pdf', 'max:10240'];

        // Desdobramentos de respostas "Não" são ignorados (exclude_unless): um radio não se desmarca.
        $dados = $request->validate([
            'conflito_interesses' => ['required', 'boolean'],
            'conflito_descricao' => ['exclude_unless:conflito_interesses,1', 'required', 'string', 'max:3000'],
            'disponibilidade_dados' => ['required', Rule::in(array_keys(TrabalhoInformacoesAdicionais::DISPONIBILIDADE))],
            'dados_repositorio' => ['exclude_unless:disponibilidade_dados,repositorio_publico', 'required', 'url', 'max:500'],
            'amostras_fisicas' => ['required', 'boolean'],
            'amostras_declaracao' => ['exclude_unless:amostras_fisicas,1', 'required', 'string', 'max:3000'],
            'aprovacao_etica' => ['required', 'boolean'],
            'aprovacao_etica_arquivo' => ['exclude_unless:aprovacao_etica,1', $atual?->aprovacao_etica_path ? 'nullable' : 'required', ...$pdf],
            'participantes' => ['required', 'boolean'],
            'certidao_etica_arquivo' => ['exclude_unless:participantes,1', $atual?->certidao_etica_path ? 'nullable' : 'required', ...$pdf],
            'uso_ia' => ['required', 'boolean'],
            'ia_ferramentas' => ['exclude_unless:uso_ia,1', 'required', 'string', 'max:500'],
            'ia_etapas' => ['exclude_unless:uso_ia,1', 'required', 'string', 'max:3000'],
            'originalidade_confirmada' => ['accepted'],
        ], [
            'conflito_descricao.required' => 'Descreva o conflito de interesses.',
            'dados_repositorio.required' => 'Informe o link do repositório dos dados.',
            'amostras_declaracao.required' => 'Informe a disponibilidade das amostras ou as restrições.',
            'aprovacao_etica_arquivo.required' => 'Envie o documento original de aprovação ética (PDF).',
            'certidao_etica_arquivo.required' => 'Envie a certidão do comitê de ética (PDF).',
            'aprovacao_etica_arquivo.mimes' => 'O documento de aprovação ética deve ser um PDF.',
            'certidao_etica_arquivo.mimes' => 'A certidão do comitê de ética deve ser um PDF.',
            'ia_ferramentas.required' => 'Informe o nome e a versão da ferramenta de IA.',
            'ia_etapas.required' => 'Informe em que etapas ou seções a IA foi usada.',
            'originalidade_confirmada.accepted' => 'Confirme a declaração de originalidade.',
        ]);

        $etica = (bool) $dados['aprovacao_etica'];
        $participantes = (bool) $dados['participantes'];

        $valores = [
            'conflito_interesses' => (bool) $dados['conflito_interesses'],
            'conflito_descricao' => $dados['conflito_interesses'] ? $dados['conflito_descricao'] : null,
            'disponibilidade_dados' => $dados['disponibilidade_dados'],
            'dados_repositorio' => $dados['disponibilidade_dados'] === 'repositorio_publico' ? $dados['dados_repositorio'] : null,
            'amostras_fisicas' => (bool) $dados['amostras_fisicas'],
            'amostras_declaracao' => $dados['amostras_fisicas'] ? $dados['amostras_declaracao'] : null,
            'aprovacao_etica' => $etica,
            'participantes' => $participantes,
            'uso_ia' => (bool) $dados['uso_ia'],
            'ia_ferramentas' => $dados['uso_ia'] ? $dados['ia_ferramentas'] : null,
            'ia_etapas' => $dados['uso_ia'] ? $dados['ia_etapas'] : null,
            'originalidade_confirmada' => true,
            'preenchido_em' => now(),
        ];

        $valores += $this->arquivo($request, 'aprovacao_etica', $etica, $atual, $trabalho);
        $valores += $this->arquivo($request, 'certidao_etica', $participantes, $atual, $trabalho);

        TrabalhoInformacoesAdicionais::updateOrCreate(['trabalho_academico_id' => $trabalho->id], $valores);

        return redirect()->route('meu-trabalho.informacoes.edit')->with('success', 'Informações adicionais salvas.');
    }

    public function etica(Request $request)
    {
        $trabalho = $this->trabalho();

        return self::baixar($trabalho->informacoesAdicionais, (string) $request->query('documento'));
    }

    /** Resposta de download de um PDF de ética (usado também pela banca e pela Secretaria). */
    public static function baixar(?TrabalhoInformacoesAdicionais $info, string $documento)
    {
        abort_unless(isset(TrabalhoInformacoesAdicionais::DOCUMENTOS[$documento]), 404);
        $campo = $documento === 'aprovacao' ? 'aprovacao_etica' : 'certidao_etica';
        $caminho = $info?->{"{$campo}_path"};
        abort_unless($caminho && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->download($caminho, $info->{"{$campo}_nome"} ?? "{$campo}.pdf");
    }

    /**
     * Grava (ou apaga) o PDF de um desdobramento: novo arquivo substitui o anterior;
     * resposta "Não" apaga o que havia.
     *
     * @return array<string, ?string>
     */
    private function arquivo(Request $request, string $campo, bool $exigido, ?TrabalhoInformacoesAdicionais $atual, TrabalhoAcademico $trabalho): array
    {
        $anterior = $atual?->{"{$campo}_path"};

        if (! $exigido) {
            if ($anterior) {
                Storage::disk('local')->delete($anterior);
            }

            return ["{$campo}_path" => null, "{$campo}_nome" => null];
        }

        $novo = $request->file("{$campo}_arquivo");
        if (! $novo) {
            return [];
        }

        $caminho = $novo->store(self::PASTA."/{$trabalho->id}", 'local');
        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }

        return ["{$campo}_path" => $caminho, "{$campo}_nome" => $novo->getClientOriginalName()];
    }

    /** Primeira visita: respostas que coincidem com os Dados da pesquisa já vêm marcadas. */
    private function preMarcada(TrabalhoAcademico $trabalho): TrabalhoInformacoesAdicionais
    {
        $pesquisa = $trabalho->dadosPesquisa;
        if (! $pesquisa) {
            return new TrabalhoInformacoesAdicionais;
        }

        $disponibilidade = [
            'nao_se_aplica' => 'nenhum_dado_novo',
            'no_trabalho' => 'no_trabalho',
            'sob_solicitacao' => 'sob_solicitacao',
            'publico' => 'repositorio_publico',
        ][$pesquisa->dados_disponibilidade] ?? null;
        $exigeEtica = $pesquisa->seres_humanos || $pesquisa->animais;

        return new TrabalhoInformacoesAdicionais([
            'disponibilidade_dados' => $disponibilidade,
            'dados_repositorio' => $disponibilidade === 'repositorio_publico' ? $pesquisa->dados_link : null,
            'aprovacao_etica' => $exigeEtica,
            'participantes' => $exigeEtica,
        ]);
    }

    private function trabalho(): TrabalhoAcademico
    {
        $user = auth()->user();
        abort_unless($user->categoria === 'discente', 403);

        $trabalho = $user->discenteProfile?->vinculosPrograma()->with('trabalhoAcademico')->get()
            ->pluck('trabalhoAcademico')->filter()->first();
        abort_unless($trabalho, 404);

        return $trabalho;
    }
}
