<?php

namespace App\Http\Controllers;

use App\Models\PeriodoQualidade;
use App\Services\SwaraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A CPA define os períodos de avaliação, as faixas das classes e os pesos (SWARA). */
class CpaPeriodoController extends CpaQualidadeController
{
    public function periodos()
    {
        $programa = $this->programaCpa();
        $periodos = PeriodoQualidade::where('programa_id', $programa->id)->with('pesosQualidade')->orderByDesc('inicio_em')->get();

        return view('cpa.periodos', compact('programa', 'periodos'));
    }

    public function update(Request $request, PeriodoQualidade $periodo)
    {
        $this->doMeuPrograma($periodo);
        // O quadriênio (nome e datas) é da Secretaria; a CPA ajusta só as faixas.
        $periodo->update($this->validarFaixas($request));

        return redirect()->route('cpa.periodos.index')->with('success', 'Faixas atualizadas.');
    }

    public function pesos(PeriodoQualidade $periodo, string $grupo)
    {
        $this->doMeuPrograma($periodo);

        return view('cpa.pesos', [
            'periodo' => $periodo,
            'grupo' => $grupo,
            'rotulos' => PeriodoQualidade::rotulos($grupo),
            'definidos' => $periodo->pesosQualidade()->where('grupo', $grupo)->get()->keyBy('chave'),
            'pesos' => $periodo->pesos($grupo),
        ]);
    }

    public function salvarPesos(Request $request, PeriodoQualidade $periodo, string $grupo, SwaraService $swara)
    {
        $this->doMeuPrograma($periodo);
        $chaves = PeriodoQualidade::chaves($grupo);
        $n = count($chaves);

        $regras = [];
        foreach ($chaves as $chave) {
            $regras["ordem.{$chave}"] = ['required', 'integer', "between:1,{$n}"];
            $regras["s.{$chave}"] = ['required', 'numeric', 'min:0', 'max:1'];
        }
        $dados = $request->validate($regras, [
            'ordem.*.required' => 'Indique a posição de cada item.',
            's.*.min' => 'O quanto é menos importante não pode ser negativo.',
            's.*.max' => 'Use valores entre 0 e 1 (ex.: 0,10 = 10% menos importante).',
        ]);
        if (count(array_unique($dados['ordem'])) !== $n) {
            throw ValidationException::withMessages(['ordem' => 'Cada posição deve ser usada uma única vez.']);
        }

        $ordem = collect($dados['ordem'])->sort()->keys()->all();
        $resultado = $swara->calcular($ordem, array_map('floatval', $dados['s']));

        DB::transaction(function () use ($periodo, $grupo, $dados, $resultado) {
            $periodo->pesosQualidade()->where('grupo', $grupo)->delete();
            foreach ($resultado as $chave => $valores) {
                $periodo->pesosQualidade()->create([
                    'grupo' => $grupo,
                    'chave' => $chave,
                    'ordem' => (int) $dados['ordem'][$chave],
                    's_j' => (int) $dados['ordem'][$chave] === 1 ? 0 : (float) $dados['s'][$chave],
                    'peso' => $valores['w_j'],
                    'definido_por' => auth()->id(),
                ]);
            }
        });

        return redirect()->route('cpa.pesos.edit', [$periodo, $grupo])->with('success', 'Pesos calculados e gravados.');
    }

    private function validarFaixas(Request $request): array
    {
        $dados = $request->validate([
            'corte_alta' => ['nullable', 'numeric', 'between:0,10'],
            'corte_boa' => ['nullable', 'numeric', 'between:0,10'],
            'corte_suficiente' => ['nullable', 'numeric', 'between:0,10'],
            'corte_pouca' => ['nullable', 'numeric', 'between:0,10'],
        ]);

        $cortes = [
            'corte_alta' => (float) ($dados['corte_alta'] ?? 8.5),
            'corte_boa' => (float) ($dados['corte_boa'] ?? 7.0),
            'corte_suficiente' => (float) ($dados['corte_suficiente'] ?? 5.5),
            'corte_pouca' => (float) ($dados['corte_pouca'] ?? 4.0),
        ];
        if (! ($cortes['corte_alta'] > $cortes['corte_boa'] && $cortes['corte_boa'] > $cortes['corte_suficiente'] && $cortes['corte_suficiente'] > $cortes['corte_pouca'])) {
            throw ValidationException::withMessages(['corte_alta' => 'As faixas devem ser decrescentes: Alta > Boa > Suficiente > Pouca.']);
        }

        return $cortes;
    }

    private function doMeuPrograma(PeriodoQualidade $periodo): void
    {
        abort_unless($periodo->programa_id === $this->programaCpa()->id, 403);
    }
}
