<?php

namespace App\Http\Controllers;

use App\Models\AreaCnpq;
use App\Models\SetorCnae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Listas de áreas do CNPq e setores CNAE usadas nos dados para o Lattes.
 * Únicas para o sistema: qualquer Secretaria inclui, edita e ativa/desativa; não há exclusão.
 */
class TabelaLattesController extends Controller
{
    public function index(Request $request)
    {
        $this->autorizar();
        $lista = $request->input('lista') === 'cnae' ? 'cnae' : 'cnpq';
        $busca = trim((string) $request->input('busca', ''));

        $itens = $this->modelo($lista)::query()
            ->when($lista === 'cnpq', fn ($q) => $q->with('pai.pai'))
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w->where('nome', 'like', "%{$busca}%")->orWhere('codigo', 'like', "{$busca}%")))
            ->orderBy('codigo')
            ->paginate(50)
            ->withQueryString();

        return view('tabelas-lattes.index', compact('lista', 'busca', 'itens'));
    }

    public function create(string $lista)
    {
        $this->autorizar();
        $lista = $this->lista($lista);
        $item = new ($this->modelo($lista))(['ativo' => true]);

        return view('tabelas-lattes.form', ['lista' => $lista, 'item' => $item, 'pais' => $this->pais($lista)]);
    }

    public function store(Request $request, string $lista)
    {
        $this->autorizar();
        $lista = $this->lista($lista);
        $this->modelo($lista)::create($this->validar($request, $lista) + ['ativo' => true]);

        return redirect()->route('tabelas-lattes.index', ['lista' => $lista])->with('success', 'Item incluído.');
    }

    public function edit(string $lista, int $id)
    {
        $this->autorizar();
        $lista = $this->lista($lista);

        return view('tabelas-lattes.form', [
            'lista' => $lista,
            'item' => $this->modelo($lista)::findOrFail($id),
            'pais' => $this->pais($lista),
        ]);
    }

    public function update(Request $request, string $lista, int $id)
    {
        $this->autorizar();
        $lista = $this->lista($lista);
        $item = $this->modelo($lista)::findOrFail($id);
        $item->update($this->validar($request, $lista, $item));

        return redirect()->route('tabelas-lattes.index', ['lista' => $lista])->with('success', 'Item atualizado.');
    }

    public function alternar(string $lista, int $id)
    {
        $this->autorizar();
        $lista = $this->lista($lista);
        $item = $this->modelo($lista)::findOrFail($id);
        $item->update(['ativo' => ! $item->ativo]);

        return back()->with('success', $item->ativo ? 'Item ativado.' : 'Item desativado.');
    }

    private function validar(Request $request, string $lista, ?Model $item = null): array
    {
        $tabela = $lista === 'cnpq' ? 'areas_cnpq' : 'setores_cnae';
        $regras = [
            'codigo' => ['required', 'string', 'max:'.($lista === 'cnpq' ? 12 : 10), Rule::unique($tabela, 'codigo')->ignore($item?->id)],
            'nome' => ['required', 'string', 'max:255'],
        ];

        if ($lista === 'cnpq') {
            $regras['nivel'] = ['required', Rule::in(array_keys(AreaCnpq::NIVEIS))];
            // O pai é sempre do nível de cima (área → grande área, subárea → área): impede ciclos.
            $nivelDoPai = AreaCnpq::NIVEL_DO_PAI[$request->input('nivel')] ?? null;
            $regras['pai_id'] = $nivelDoPai === null
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('areas_cnpq', 'id')->where('nivel', $nivelDoPai)];
        } else {
            $regras['secao'] = ['nullable', 'string', 'size:1'];
        }

        return $request->validate($regras, [
            'codigo.unique' => 'Já existe um item com este código.',
            'pai_id.prohibited' => 'A grande área não pertence a outro item.',
            'pai_id.exists' => 'Escolha um item do nível de cima (área → grande área; subárea → área).',
        ]);
    }

    private function pais(string $lista)
    {
        return $lista === 'cnpq'
            ? AreaCnpq::whereIn('nivel', ['grande_area', 'area'])->orderBy('codigo')->get()
            : collect();
    }

    /** @return class-string<Model> */
    private function modelo(string $lista): string
    {
        return $lista === 'cnpq' ? AreaCnpq::class : SetorCnae::class;
    }

    private function lista(string $lista): string
    {
        abort_unless(in_array($lista, ['cnpq', 'cnae'], true), 404);

        return $lista;
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }
}
