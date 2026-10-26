<?php

namespace App\Http\Controllers;

use App\Exceptions\ProjetoLatexInvalidoException;
use App\Models\DiscenteProgramaVinculo;
use App\Models\Programa;
use App\Models\ProgramaTemplateLatex;
use App\Services\Latex\ElementosVitais;
use App\Services\Latex\ExtratorElementosLatexService;
use App\Services\Latex\MapaTemplateOllamaService;
use App\Services\Latex\ProjetoLatexZipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProgramaTemplateLatexController extends Controller
{
    private function autorizarSecretaria(): void
    {
        abort_unless(auth()->user()->categoria === 'sec', 403);
    }

    public function index()
    {
        $this->autorizarSecretaria();
        $programa = $this->programaDaSecretaria();
        $template = $programa->templateLatex()->with('enviadoPor')->first();

        return view('programas.template-latex', compact('programa', 'template'));
    }

    public function store(
        Request $request,
        ProjetoLatexZipService $servico,
        ExtratorElementosLatexService $extrator,
        MapaTemplateOllamaService $ollama,
    ) {
        $this->autorizarSecretaria();
        $programa = $this->programaDaSecretaria();

        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ], [
            'arquivo.required' => 'Selecione o arquivo .zip do template.',
            'arquivo.mimes' => 'O arquivo deve ser um .zip com o projeto LaTeX.',
            'arquivo.max' => 'O arquivo não pode ultrapassar 50 MB.',
        ]);

        $arquivo = $request->file('arquivo');
        $tmp = storage_path('app/private/tmp/'.Str::uuid());
        File::ensureDirectoryExists($tmp);

        try {
            $projeto = $servico->extrair($arquivo->path(), $tmp, 'dissertacao.tex');
            $classe = $projeto->classeDocumento();
            $texPrincipal = $projeto->texPrincipal;
            $fonte = $projeto->fonteExpandido();
            $mapa = $extrator->descobrirMapa($fonte);
            $motor = $extrator->detectarMotor($fonte);
        } catch (ProjetoLatexInvalidoException $e) {
            throw ValidationException::withMessages(['arquivo' => $e->getMessage()]);
        } finally {
            File::deleteDirectory($tmp);
        }

        // o Ollama só completa o que as regras não acharam; falha dele devolve []
        $faltantes = array_values(array_diff(ElementosVitais::todos(), array_keys($mapa)));
        if ($faltantes !== []) {
            $mapa += $ollama->sugerir($fonte, $faltantes);
        }

        $destino = "programas/{$programa->id}/template-latex/template.zip";
        Storage::disk('local')->put($destino, file_get_contents($arquivo->path()));

        ProgramaTemplateLatex::updateOrCreate(
            ['programa_id' => $programa->id],
            [
                'arquivo_path' => $destino,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'hash_sha256' => hash_file('sha256', $arquivo->path()),
                'tex_principal' => $texPrincipal,
                'classe_documento' => $classe,
                'motor' => $motor,
                'mapa_elementos' => $mapa,
                'enviado_por' => auth()->id(),
                'enviado_em' => now(),
            ]
        );

        return redirect()->route('programa-template.index')
            ->with('status', 'Template LaTeX enviado.');
    }

    public function download(Programa $programa)
    {
        $user = auth()->user();

        if ($user->categoria === 'sec') {
            $this->garantirProgramaDaSecretaria($programa->id);
        } elseif ($user->categoria === 'discente') {
            $vinculado = DiscenteProgramaVinculo::query()
                ->where('programa_id', $programa->id)
                ->where('ativo', true)
                ->whereHas('discenteProfile', fn ($q) => $q->where('user_id', $user->id))
                ->exists();
            abort_unless($vinculado, 403);
        } else {
            abort(403);
        }

        $template = $programa->templateLatex;
        abort_unless($template && Storage::disk('local')->exists($template->arquivo_path), 404);

        return Storage::disk('local')->download($template->arquivo_path, $template->arquivo_nome_original);
    }
}
