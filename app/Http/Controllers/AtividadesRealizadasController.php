<?php

namespace App\Http\Controllers;

use App\Models\AtividadeFormativa;
use App\Models\AtividadeRealizada;
use App\Models\TrabalhoAcademico;
use App\Services\AtividadesFormativasService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Atividades formativas realizadas, lançadas pelo estudante; sem as exigidas, o pedido não vai à homologação. */
class AtividadesRealizadasController extends Controller
{
    public const MENSAGEM_TRAVADO = 'Seu pedido está aguardando homologação; as atividades não podem ser alteradas agora.';

    public function __construct(
        private readonly AtividadesFormativasService $atividades,
    ) {}

    public function index()
    {
        $trabalho = $this->trabalho();
        $atividades = $this->atividades->exigidas($trabalho, 'defesa');
        $lancamentos = $trabalho->atividadesRealizadas()->get()->groupBy('atividade_formativa_id');
        $travado = $this->atividades->travado($trabalho);

        return view('meu-trabalho.atividades-formativas', compact('trabalho', 'atividades', 'lancamentos', 'travado'));
    }

    public function store(Request $request)
    {
        $trabalho = $this->trabalho();
        $this->exigirDestravado($trabalho);

        $dados = $request->validate([
            'atividade_formativa_id' => ['required', Rule::exists('atividades_formativas', 'id')
                ->where('programa_id', $trabalho->programa_id)
                ->where('ativo', true)
                ->where('informada_por', 'estudante')],
            'descricao' => ['required', 'string', 'max:1000'],
            'link' => ['nullable', 'url', 'max:500'],
        ], [
            'atividade_formativa_id.exists' => 'Escolha uma atividade que você mesmo informa.',
            'descricao.required' => 'Descreva a atividade realizada.',
            'link.url' => 'Informe um link completo (https://…).',
        ]);

        $trabalho->atividadesRealizadas()->create($dados);

        return redirect()->route('meu-trabalho.atividades.index')->with('success', 'Atividade lançada.');
    }

    public function destroy(AtividadeRealizada $atividadeRealizada)
    {
        $trabalho = $this->trabalho();
        abort_unless($atividadeRealizada->trabalho_academico_id === $trabalho->id, 404);
        $this->exigirDestravado($trabalho);

        if ($atividadeRealizada->conferencia === 'confirmada') {
            throw ValidationException::withMessages(['atividade' => 'Atividade já confirmada pela Secretaria não pode ser excluída.']);
        }

        $atividadeRealizada->delete();

        return redirect()->route('meu-trabalho.atividades.index')->with('success', 'Lançamento excluído.');
    }

    private function exigirDestravado(TrabalhoAcademico $trabalho): void
    {
        if ($this->atividades->travado($trabalho)) {
            throw ValidationException::withMessages(['travado' => self::MENSAGEM_TRAVADO]);
        }
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
