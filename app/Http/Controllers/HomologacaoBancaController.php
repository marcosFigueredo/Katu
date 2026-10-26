<?php

namespace App\Http\Controllers;

use App\Models\Banca;
use App\Services\BancaNotificacaoService;
use App\Services\HomologacaoBancaService;
use App\Services\VerificacaoBancaService;
use Illuminate\Http\Request;

class HomologacaoBancaController extends Controller
{
    public function __construct(
        private readonly HomologacaoBancaService $service,
        private readonly BancaNotificacaoService $notificacao,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programaId = auth()->user()->programaUsuarios()->where('ativo', true)->value('programa_id');

        $bancas = Banca::query()
            ->whereIn('homologacao_status', ['aguardando', 'devolvida'])
            ->whereHas('trabalhoAcademico', fn ($query) => $query->where('programa_id', $programaId))
            ->with([
                'trabalhoAcademico.discenteVinculo.discenteProfile.user',
                'trabalhoAcademico.programa',
                'membros',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $verificacoes = $bancas->mapWithKeys(
            fn (Banca $banca) => [$banca->id => app(VerificacaoBancaService::class)->verificarBanca($banca)]
        );

        return view('bancas.homologacao', compact('bancas', 'verificacoes'));
    }

    public function homologar(Banca $banca)
    {
        $this->autorizarSecretaria();
        $this->garantirPrograma($banca);

        $this->service->homologar($banca, auth()->user());
        $this->notificacao->enviarCadastro($banca);

        return back()->with('status', 'Banca homologada. O envio do trabalho foi liberado.');
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

        $this->service->devolver($banca, auth()->user(), $data['observacoes']);

        return back()->with('status', 'Banca devolvida ao discente para ajustes.');
    }

    private function garantirPrograma(Banca $banca): void
    {
        $programaId = auth()->user()->programaUsuarios()->where('ativo', true)->value('programa_id');
        abort_unless($banca->trabalhoAcademico()->where('programa_id', $programaId)->exists(), 404);
    }
}
