<?php

namespace App\Http\Controllers;

use App\Models\Barema;
use App\Models\SwaraSessao;
use App\Services\SwaraService;
use Illuminate\Http\Request;

class SwaraSessaoController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    public function index(Barema $barema)
    {
        $this->autorizarSecretaria();

        $sessoes = SwaraSessao::with('julgamentos.baremaItem')
            ->where('barema_id', $barema->id)
            ->orderByDesc('created_at')
            ->get();

        return view('swara.index', compact('barema', 'sessoes'));
    }

    public function create(Barema $barema)
    {
        $this->autorizarSecretaria();

        $barema->loadMissing('itens');
        $itens = $barema->itens->where('ativo', true)->sortBy('ordem')->values();

        return view('swara.create', compact('barema', 'itens'));
    }

    public function store(Request $request, Barema $barema, SwaraService $swaraService)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'nome'         => ['required', 'string', 'max:200'],
            'descricao'    => ['nullable', 'string'],
            'aprovado_por' => ['nullable', 'string', 'max:200'],
            'ordem'        => ['required', 'array'],
            'ordem.*'      => ['integer', 'exists:barema_itens,id'],
            's_j'          => ['nullable', 'array'],
            's_j.*'        => ['nullable', 'numeric', 'min:0', 'max:5'],
        ]);

        $ordemItemIds = array_map('intval', $data['ordem']);
        $sJValues     = collect($data['s_j'] ?? [])->mapWithKeys(fn ($v, $k) => [(int) $k => (float) ($v ?? 0)])->all();

        $sessao = $swaraService->criarSessao(
            $barema,
            $data['nome'],
            $data['descricao'] ?? null,
            $data['aprovado_por'] ?? null,
            $ordemItemIds,
            $sJValues,
        );

        return redirect()->route('swara.show', [$barema, $sessao])
            ->with('success', 'Sessão SWARA criada com sucesso.');
    }

    public function show(Barema $barema, SwaraSessao $sessao)
    {
        $this->autorizarSecretaria();

        $sessao->loadMissing('julgamentos.baremaItem');

        return view('swara.show', compact('barema', 'sessao'));
    }

    public function aplicar(Barema $barema, SwaraSessao $sessao, SwaraService $swaraService)
    {
        $this->autorizarSecretaria();

        if ($sessao->barema_id !== $barema->id) {
            abort(404);
        }

        $swaraService->aplicar($sessao);

        return redirect()->route('swara.show', [$barema, $sessao])
            ->with('success', 'Pesos SWARA aplicados ao barema com sucesso.');
    }
}
