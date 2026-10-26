<?php

namespace App\Http\Controllers;

use App\Models\AutoavaliacaoIndicador;
use App\Models\AutoavaliacaoPeriodo;
use App\Models\PerguntaEstrategica;
use App\Models\Programa;
use App\Services\PerguntaEstrategicaService;
use Illuminate\Http\Request;

class PerguntaEstrategicaController extends Controller
{
    private function autorizarSecretaria(): void
    {
        if (auth()->user()->categoria !== 'sec') {
            abort(403);
        }
    }

    private function programa(): Programa
    {
        return $this->programaDaSecretaria();
    }

    public function index(Request $request, PerguntaEstrategicaService $service)
    {
        $this->autorizarSecretaria();

        $programa = $this->programa();

        $periodos = AutoavaliacaoPeriodo::with('programa')
            ->where('programa_id', $programa->id)
            ->where('status', 'concluido')
            ->orderByDesc('data_inicio')
            ->get();

        $periodoAtual = $request->filled('periodo')
            ? $periodos->firstWhere('id', (int) $request->periodo)
            : $periodos->first();

        $codigosDisponiveis = AutoavaliacaoIndicador::query()
            ->when($periodoAtual, fn ($q) => $q->where('autoavaliacao_periodo_id', $periodoAtual->id))
            ->orderBy('codigo')
            ->pluck('nome', 'codigo');

        $perguntas = PerguntaEstrategica::with('indicadores')
            ->where('programa_id', $programa->id)
            ->where('ativa', true)
            ->orderByRaw("origem = 'capes' desc")
            ->orderBy('item_capes')
            ->orderBy('id')
            ->get()
            ->map(fn ($pergunta) => [
                'pergunta'  => $pergunta,
                'resultado' => $service->avaliar($pergunta, $periodoAtual),
            ]);

        return view('perguntas-estrategicas.index', compact(
            'perguntas', 'periodos', 'periodoAtual', 'codigosDisponiveis'
        ));
    }

    public function create()
    {
        $this->autorizarSecretaria();

        $codigosDisponiveis = AutoavaliacaoIndicador::query()
            ->select('codigo', 'nome')
            ->distinct()
            ->orderBy('codigo')
            ->get();

        return view('perguntas-estrategicas.create', compact('codigosDisponiveis'));
    }

    public function store(Request $request)
    {
        $this->autorizarSecretaria();

        $data = $request->validate([
            'item_capes'                  => ['nullable', 'string', 'max:20'],
            'texto'                       => ['required', 'string'],
            'indicadores'                 => ['required', 'array', 'min:1'],
            'indicadores.*.codigo'        => ['required', 'string', 'max:20'],
            'indicadores.*.direcao'       => ['required', 'in:maior_melhor,menor_melhor'],
            'indicadores.*.limiar_verde'  => ['nullable', 'numeric'],
            'indicadores.*.limiar_amarelo'=> ['nullable', 'numeric'],
        ]);

        $programa = $this->programa();

        $pergunta = PerguntaEstrategica::create([
            'programa_id'   => $programa->id,
            'item_capes'    => $data['item_capes'] ?? null,
            'texto'         => $data['texto'],
            'origem'        => 'comissao',
            'ativa'         => true,
            'criado_por_id' => auth()->id(),
        ]);

        foreach ($data['indicadores'] as $vinculo) {
            $pergunta->indicadores()->create([
                'indicador_codigo' => $vinculo['codigo'],
                'direcao'          => $vinculo['direcao'],
                'limiar_verde'     => $vinculo['limiar_verde'] ?? null,
                'limiar_amarelo'   => $vinculo['limiar_amarelo'] ?? null,
            ]);
        }

        return redirect()->route('perguntas-estrategicas.index')
            ->with('success', 'Pergunta estratégica cadastrada.');
    }

    public function edit(PerguntaEstrategica $pergunta)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($pergunta->programa_id);

        $pergunta->loadMissing('indicadores');

        $codigosDisponiveis = AutoavaliacaoIndicador::query()
            ->select('codigo', 'nome')
            ->distinct()
            ->orderBy('codigo')
            ->get();

        return view('perguntas-estrategicas.edit', [
            'pergunta'           => $pergunta,
            'codigosDisponiveis' => $codigosDisponiveis,
        ]);
    }

    public function update(Request $request, PerguntaEstrategica $pergunta)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($pergunta->programa_id);

        $data = $request->validate([
            'item_capes'                  => ['nullable', 'string', 'max:20'],
            'texto'                       => ['required', 'string'],
            'ativa'                       => ['nullable', 'boolean'],
            'indicadores'                 => ['required', 'array', 'min:1'],
            'indicadores.*.codigo'        => ['required', 'string', 'max:20'],
            'indicadores.*.direcao'       => ['required', 'in:maior_melhor,menor_melhor'],
            'indicadores.*.limiar_verde'  => ['nullable', 'numeric'],
            'indicadores.*.limiar_amarelo'=> ['nullable', 'numeric'],
        ]);

        $pergunta->update([
            'item_capes' => $data['item_capes'] ?? null,
            'texto'      => $data['texto'],
            'ativa'      => $request->boolean('ativa', true),
        ]);

        $pergunta->indicadores()->delete();

        foreach ($data['indicadores'] as $vinculo) {
            $pergunta->indicadores()->create([
                'indicador_codigo' => $vinculo['codigo'],
                'direcao'          => $vinculo['direcao'],
                'limiar_verde'     => $vinculo['limiar_verde'] ?? null,
                'limiar_amarelo'   => $vinculo['limiar_amarelo'] ?? null,
            ]);
        }

        return redirect()->route('perguntas-estrategicas.index')
            ->with('success', 'Pergunta estratégica atualizada.');
    }

    public function destroy(PerguntaEstrategica $pergunta)
    {
        $this->autorizarSecretaria();
        $this->garantirProgramaDaSecretaria($pergunta->programa_id);

        // Perguntas oficiais da CAPES não podem ser removidas — apenas desativadas via update.
        if ($pergunta->origem === 'capes') {
            abort(403, 'Perguntas da CAPES não podem ser excluídas, apenas desativadas.');
        }

        $pergunta->delete();

        return redirect()->route('perguntas-estrategicas.index')
            ->with('success', 'Pergunta estratégica excluída.');
    }
}
