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

        // Campos que a resposta tornou sem efeito são ignorados (exclude_*): um radio não se desmarca,
        // e o valor que sobrou no formulário não pode impedir o salvamento.
        $dados = $request->validate([
            'codigo' => ['required', Rule::in(array_keys(TrabalhoDadosPesquisa::CODIGO))],
            'codigo_link' => ['exclude_unless:codigo,publico', 'required', 'url', 'max:500'],
            'codigo_justificativa' => ['exclude_unless:codigo,nao_compartilhavel', 'required', 'string', 'max:2000'],
            'dados_disponibilidade' => ['required', Rule::in(array_keys(TrabalhoDadosPesquisa::DADOS_DISPONIBILIDADE))],
            'dados_tipos' => ['exclude_if:dados_disponibilidade,nao_se_aplica', 'required', 'array'],
            'dados_tipos.*' => ['exclude_if:dados_disponibilidade,nao_se_aplica', Rule::in(array_keys(TrabalhoDadosPesquisa::DADOS_TIPOS))],
            'dados_link' => ['exclude_unless:dados_disponibilidade,publico', 'required', 'url', 'max:500'],
            'dados_justificativa' => ['exclude_unless:dados_disponibilidade,nao_compartilhavel', 'required', 'string', 'max:2000'],
            'seres_humanos' => ['required', 'boolean'],
            // CAAE da Plataforma Brasil: 00000000.0.0000.0000
            'caae' => ['exclude_unless:seres_humanos,1', 'required', 'regex:/^\d{8}\.\d\.\d{4}\.\d{4}$/'],
            'parecer_numero' => ['exclude_unless:seres_humanos,1', 'required', 'string', 'max:30'],
            'tcle' => ['exclude_unless:seres_humanos,1', 'required', Rule::in(array_keys(TrabalhoDadosPesquisa::TCLE))],
            'tcle_justificativa' => ['exclude_unless:seres_humanos,1', 'exclude_unless:tcle,dispensa', 'required', 'string', 'max:2000'],
            'animais' => ['required', 'boolean'],
            'ceua_protocolo' => ['exclude_unless:animais,1', 'required', 'string', 'max:60'],
        ], [
            'codigo_link.required' => 'Informe o link do repositório do código.',
            'codigo_justificativa.required' => 'Justifique por que o código não pode ser compartilhado.',
            'dados_tipos.required' => 'Marque que dados foram usados.',
            'dados_link.required' => 'Informe o link ou DOI do repositório dos dados.',
            'dados_justificativa.required' => 'Justifique por que os dados não podem ser compartilhados.',
            'caae.required' => 'Informe o CAAE da Plataforma Brasil.',
            'caae.regex' => 'O CAAE tem o formato 00000000.0.0000.0000.',
            'parecer_numero.required' => 'Informe o número do parecer do Comitê de Ética.',
            'tcle.required' => 'Informe se houve Termo de Consentimento ou dispensa.',
            'tcle_justificativa.required' => 'Justifique a dispensa do Termo de Consentimento.',
            'ceua_protocolo.required' => 'Informe o número do protocolo da Comissão de Ética no Uso de Animais.',
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
