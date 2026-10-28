<?php

namespace App\Http\Controllers;

use App\Models\Banca;
use App\Services\HomologacaoBancaService;
use Illuminate\Http\Request;

class HomologacaoBancaController extends Controller
{
    public function __construct(
        private readonly HomologacaoBancaService $service,
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

        return view('bancas.homologacao-show', compact('banca', 'respostas', 'analise'));
    }

    public function responder(Request $request, Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $this->service->responder($banca, (array) $request->input('respostas', []), auth()->user());

        return back()->with('status', 'Respostas da CPA salvas.');
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
