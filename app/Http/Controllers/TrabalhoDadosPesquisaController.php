<?php

namespace App\Http\Controllers;

use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDadosPesquisa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Código, dados e ética da pesquisa, informados pelo estudante; exigidos para enviar o pedido à homologação. */
class TrabalhoDadosPesquisaController extends Controller
{
    public function edit()
    {
        $trabalho = $this->trabalho();
        $dados = $trabalho->dadosPesquisa ?? new TrabalhoDadosPesquisa(['dados_tipos' => []]);

        return view('meu-trabalho.dados-pesquisa', compact('trabalho', 'dados'));
    }

    public function update(Request $request)
    {
        $trabalho = $this->trabalho();

        $dados = $request->validate([
            'codigo' => ['required', Rule::in(array_keys(TrabalhoDadosPesquisa::CODIGO))],
            'codigo_link' => ['nullable', 'required_if:codigo,publico', 'url', 'max:500'],
            'codigo_justificativa' => ['nullable', 'required_if:codigo,nao_compartilhavel', 'string', 'max:2000'],
            'dados_disponibilidade' => ['required', Rule::in(array_keys(TrabalhoDadosPesquisa::DADOS_DISPONIBILIDADE))],
            'dados_tipos' => ['nullable', 'array', 'required_unless:dados_disponibilidade,nao_se_aplica'],
            'dados_tipos.*' => [Rule::in(array_keys(TrabalhoDadosPesquisa::DADOS_TIPOS))],
            'dados_link' => ['nullable', 'required_if:dados_disponibilidade,publico', 'url', 'max:500'],
            'dados_justificativa' => ['nullable', 'required_if:dados_disponibilidade,nao_compartilhavel', 'string', 'max:2000'],
            'seres_humanos' => ['required', 'boolean'],
            // CAAE da Plataforma Brasil: 00000000.0.0000.0000
            'caae' => ['nullable', 'required_if:seres_humanos,1', 'regex:/^\d{8}\.\d\.\d{4}\.\d{4}$/'],
            'parecer_numero' => ['nullable', 'required_if:seres_humanos,1', 'string', 'max:30'],
            'tcle' => ['nullable', 'required_if:seres_humanos,1', Rule::in(array_keys(TrabalhoDadosPesquisa::TCLE))],
            'tcle_justificativa' => ['nullable', 'required_if:tcle,dispensa', 'string', 'max:2000'],
            'animais' => ['required', 'boolean'],
            'ceua_protocolo' => ['nullable', 'required_if:animais,1', 'string', 'max:60'],
        ], [
            'codigo_link.required_if' => 'Informe o link do repositório do código.',
            'codigo_justificativa.required_if' => 'Justifique por que o código não pode ser compartilhado.',
            'dados_tipos.required_unless' => 'Marque que dados foram usados.',
            'dados_link.required_if' => 'Informe o link ou DOI do repositório dos dados.',
            'dados_justificativa.required_if' => 'Justifique por que os dados não podem ser compartilhados.',
            'caae.required_if' => 'Informe o CAAE da Plataforma Brasil.',
            'caae.regex' => 'O CAAE tem o formato 00000000.0.0000.0000.',
            'parecer_numero.required_if' => 'Informe o número do parecer do Comitê de Ética.',
            'tcle.required_if' => 'Informe se houve Termo de Consentimento ou dispensa.',
            'tcle_justificativa.required_if' => 'Justifique a dispensa do Termo de Consentimento.',
            'ceua_protocolo.required_if' => 'Informe o número do protocolo da Comissão de Ética no Uso de Animais.',
        ]);

        $codigo = $dados['codigo'];
        $disponibilidade = $dados['dados_disponibilidade'];
        $humanos = (bool) $dados['seres_humanos'];
        $tcle = $humanos ? $dados['tcle'] : null;
        $animais = (bool) $dados['animais'];

        // Campos que a resposta tornou sem efeito são limpos, para não ficarem dados contraditórios.
        TrabalhoDadosPesquisa::updateOrCreate(['trabalho_academico_id' => $trabalho->id], [
            'codigo' => $codigo,
            'codigo_link' => $codigo === 'publico' ? $dados['codigo_link'] : null,
            'codigo_justificativa' => $codigo === 'nao_compartilhavel' ? $dados['codigo_justificativa'] : null,
            'dados_tipos' => $disponibilidade === 'nao_se_aplica' ? [] : array_values($dados['dados_tipos']),
            'dados_disponibilidade' => $disponibilidade,
            'dados_link' => $disponibilidade === 'publico' ? $dados['dados_link'] : null,
            'dados_justificativa' => $disponibilidade === 'nao_compartilhavel' ? $dados['dados_justificativa'] : null,
            'seres_humanos' => $humanos,
            'caae' => $humanos ? $dados['caae'] : null,
            'parecer_numero' => $humanos ? $dados['parecer_numero'] : null,
            'tcle' => $tcle,
            'tcle_justificativa' => $tcle === 'dispensa' ? $dados['tcle_justificativa'] : null,
            'animais' => $animais,
            'ceua_protocolo' => $animais ? $dados['ceua_protocolo'] : null,
            'preenchido_em' => now(),
        ]);

        return redirect()->route('meu-trabalho.dados-pesquisa.edit')->with('success', 'Dados da pesquisa salvos.');
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
