<?php

namespace App\Http\Controllers;

use App\Models\AtividadeRealizada;
use App\Models\Banca;
use App\Models\ProducaoPublicacao;
use App\Services\AtividadesFormativasService;
use App\Services\HomologacaoBancaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HomologacaoBancaController extends Controller
{
    public function __construct(
        private readonly HomologacaoBancaService $service,
        private readonly AtividadesFormativasService $atividades,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    /** A fila de homologação faz parte da tela Bancas. */
    public function index()
    {
        $this->autorizarSecretaria();

        return redirect()->route('bancas.index');
    }

    public function show(Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $banca->load([
            'trabalhoAcademico.discenteVinculo.discenteProfile.user',
            'trabalhoAcademico.programa.instituicao',
            'trabalhoAcademico.orientacoes.docenteProgramaVinculo.docenteProfile.user',
            'membros.docenteProgramaVinculo.docenteProfile.user',
            'documentoHomologacao.submissaoLatex',
            'homologadaPor',
            'devolvidaPor',
        ]);

        $respostas = $this->service->respostasAtuais($banca);
        $analise   = $this->service->podeHomologar($banca);
        $atividades = $this->atividades->exigidas($banca->trabalhoAcademico, $banca->tipo);
        $lancamentos = $banca->trabalhoAcademico->atividadesRealizadas()->get()->groupBy('atividade_formativa_id');
        $publicacoes = $banca->tipo === 'defesa' ? $banca->trabalhoAcademico->publicacoes()->orderByDesc('ano')->get() : collect();

        return view('bancas.homologacao-show', compact('banca', 'respostas', 'analise', 'atividades', 'lancamentos', 'publicacoes'));
    }

    public function responder(Request $request, Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $this->service->responder($banca, (array) $request->input('respostas', []), auth()->user());

        return back()->with('status', 'Respostas da CPA salvas.');
    }

    /** Confere os lançamentos de atividade formativa e registra as que a Secretaria informa. */
    public function conferirAtividades(Request $request, Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $dados = $request->validate([
            'conferencias' => ['array'],
            'conferencias.*' => [Rule::in(array_keys(AtividadeRealizada::CONFERENCIA))],
            'registrar' => ['array'],
        ]);
        $trabalho = $banca->trabalhoAcademico;
        $daSecretaria = $this->atividades->exigidas($trabalho, $banca->tipo)->where('informada_por', 'secretaria')->pluck('id')->all();

        DB::transaction(function () use ($dados, $trabalho, $daSecretaria) {
            foreach ($dados['conferencias'] ?? [] as $id => $conferencia) {
                // Só lançamentos deste trabalho; os demais são ignorados.
                $trabalho->atividadesRealizadas()->whereKey($id)->update([
                    'conferencia' => $conferencia, 'conferida_por' => auth()->id(), 'conferida_em' => now(),
                ]);
            }

            foreach (array_keys(array_filter($dados['registrar'] ?? [])) as $atividadeId) {
                if (in_array((int) $atividadeId, $daSecretaria, true)) {
                    $trabalho->atividadesRealizadas()->create([
                        'atividade_formativa_id' => $atividadeId,
                        'descricao' => 'Registrada pela Secretaria',
                        'conferencia' => 'confirmada', 'conferida_por' => auth()->id(), 'conferida_em' => now(),
                    ]);
                }
            }
        });

        return back()->with('status', 'Conferência das atividades salva.');
    }

    /** Marca as publicações da defesa (situação e categorização para análise futura). */
    public function conferirPublicacoes(Request $request, Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $regras = [
            'publicacoes' => ['required', 'array'],
            'publicacoes.*.situacao' => ['required', Rule::in(array_keys(ProducaoPublicacao::SITUACOES))],
        ];
        foreach (array_keys(ProducaoPublicacao::MARCACOES) as $campo) {
            $regras["publicacoes.*.{$campo}"] = ['nullable', 'boolean'];
        }
        $dados = $request->validate($regras);

        DB::transaction(function () use ($dados, $banca) {
            foreach ($dados['publicacoes'] as $id => $marcacoes) {
                $valores = ['situacao' => $marcacoes['situacao'], 'conferida_por' => auth()->id(), 'conferida_em' => now()];
                foreach (array_keys(ProducaoPublicacao::MARCACOES) as $campo) {
                    $valores[$campo] = (bool) ($marcacoes[$campo] ?? false);
                }
                $banca->trabalhoAcademico->publicacoes()->whereKey($id)->update($valores);
            }
        });

        return back()->with('status', 'Publicações conferidas.');
    }

    public function homologar(Request $request, Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        if ($request->has('respostas')) {
            $this->service->responder($banca, (array) $request->input('respostas', []), auth()->user());
        }

        $this->service->homologar($banca, auth()->user());

        return back()->with('status', 'Banca homologada. O PDF foi enviado aos membros da banca e a ata e a folha de assinaturas foram enviadas ao orientador.');
    }

    public function devolver(Request $request, Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $data = $request->validate([
            'observacoes' => ['required', 'string', 'max:3000'],
        ], [
            'observacoes.required' => 'Informe o motivo da devolução.',
        ]);

        if ($request->has('respostas')) {
            $this->service->responder($banca, (array) $request->input('respostas', []), auth()->user());
        }

        $this->service->devolver($banca, auth()->user(), $data['observacoes']);

        return back()->with('status', 'Banca devolvida ao discente para ajustes.');
    }

    private function garantirPrograma(Banca $banca): void
    {
        $programaId = auth()->user()->programaUsuarios()->where('ativo', true)->value('programa_id');
        abort_unless($banca->trabalhoAcademico()->where('programa_id', $programaId)->exists(), 404);
    }
}
