<?php

namespace App\Http\Controllers;

use App\Mail\PrimeiroAcessoUsuarioMail;
use App\Services\CadastroDiscenteService;
use App\Services\ContextoSecretaria;
use App\Services\ImportacaoDiscentesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ImportacaoDiscenteController extends Controller
{
    public function __construct(
        private readonly ContextoSecretaria $contexto,
        private readonly ImportacaoDiscentesService $importacao,
        private readonly CadastroDiscenteService $cadastro,
    ) {
    }

    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function create()
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma(auth()->user());
        $cursos = $programa->cursos()->where('ativo', true)->get();

        return view('usuarios.importar', compact('programa', 'cursos'));
    }

    public function analisar(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());
        $request->validate(['arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'arquivo.required' => 'Selecione o arquivo CSV.',
            'arquivo.mimes' => 'Envie um arquivo .csv.',
            'arquivo.max' => 'O arquivo deve ter no máximo 2 MB.',
        ]);

        $resultado = $this->importacao->analisar($request->file('arquivo')->get(), $programa);
        if ($resultado['erro_arquivo']) {
            return redirect()->route('usuarios.importar')->withErrors(['arquivo' => $resultado['erro_arquivo']]);
        }

        $token = (string) Str::uuid();
        Cache::put("importacao-discentes:$token", [
            'user_id' => $request->user()->id,
            'programa_id' => $programa->id,
            'conteudo' => $request->file('arquivo')->get(),
        ], now()->addMinutes(30));
        session(['importacao_discentes_token' => $token]);

        $linhas = collect($resultado['linhas']);

        return view('usuarios.importar-previa', compact('linhas', 'token'));
    }

    public function confirmar(Request $request)
    {
        $this->autorizarSecretaria();
        $programa = $this->contexto->exigirPrograma($request->user());
        $token = (string) $request->input('token');
        $previa = Cache::get("importacao-discentes:$token");

        if (! $previa || $previa['user_id'] !== $request->user()->id || $previa['programa_id'] !== $programa->id) {
            return redirect()->route('usuarios.importar')
                ->withErrors(['arquivo' => 'A prévia expirou ou não pertence a você. Envie o arquivo novamente.']);
        }
        Cache::forget("importacao-discentes:$token");

        // Revalida: um e-mail pode ter sido cadastrado depois da prévia.
        $linhas = collect($this->importacao->analisar($previa['conteudo'], $programa)['linhas']);

        $criados = DB::transaction(fn () => $linhas->where('status', 'valida')
            ->map(fn ($linha) => $this->cadastro->criar($programa, $linha['dados']))
            ->values());

        foreach ($criados as [$usuario, $senha]) {
            Mail::to($usuario->email)->queue(new PrimeiroAcessoUsuarioMail($usuario, $senha, route('login')));
        }

        $naoImportadas = $linhas->where('status', '!=', 'valida')->values();

        return view('usuarios.importar-resultado', [
            'importados' => $criados->count(),
            'naoImportadas' => $naoImportadas,
        ]);
    }
}
