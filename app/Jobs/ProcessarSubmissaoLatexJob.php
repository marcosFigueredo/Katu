<?php

namespace App\Jobs;

use App\Exceptions\ProjetoLatexInvalidoException;
use App\Models\TrabalhoSubmissaoLatex;
use App\Services\Latex\CompiladorLatex;
use App\Services\Latex\ConformidadeCadastroService;
use App\Services\Latex\ElementosVitais;
use App\Services\Latex\ExtratorElementosLatexService;
use App\Services\Latex\FonteLatex;
use App\Services\Latex\ProjetoLatex;
use App\Services\Latex\ProjetoLatexZipService;
use App\Services\Latex\ResultadoCompilacao;
use App\Support\NomePessoa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Processa a submissão LaTeX do discente: extrai o zip com segurança, lê os elementos
 * vitais, confere com o cadastro (banca do pedido), compila a versão da banca com linhas
 * numeradas e decide ok / não ok (regras 5-7, D2, D3, D4, D10).
 */
class ProcessarSubmissaoLatexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const AVISO_SEM_NUMERACAO = 'Numeração de linhas indisponível com esta classe';

    public const MENSAGEM_ERRO = 'Erro inesperado no processamento. Tente novamente ou procure a Secretaria.';

    public int $tries = 1;

    /** Duas compilações de até 180 s cada, mais extração e cópias. */
    public int $timeout = 600;

    private const MAX_LOG = 20000;

    public function __construct(public TrabalhoSubmissaoLatex $submissao) {}

    public function handle(
        ProjetoLatexZipService $zipService,
        ExtratorElementosLatexService $extrator,
        CompiladorLatex $compilador,
        ConformidadeCadastroService $conformidade,
    ): void {
        $submissao = $this->submissao;
        // diretório novo e exclusivo: o serviço do zip apaga o conteúdo do destino em caso de falha
        $tmp = storage_path('app/private/tmp/submissao-latex-'.$submissao->id.'-'.Str::uuid());
        File::ensureDirectoryExists($tmp);

        try {
            $this->processar($submissao, $tmp, $zipService, $extrator, $compilador, $conformidade);
        } catch (Throwable $e) {
            report($e);
            $this->marcarErro($e);
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    public function failed(?Throwable $e): void
    {
        if ($e !== null) {
            report($e);
        }
        $this->marcarErro($e);
    }

    private function processar(
        TrabalhoSubmissaoLatex $submissao,
        string $tmp,
        ProjetoLatexZipService $zipService,
        ExtratorElementosLatexService $extrator,
        CompiladorLatex $compilador,
        ConformidadeCadastroService $conformidade,
    ): void {
        $trabalho = $submissao->trabalhoAcademico()->with('programa.templateLatex')->firstOrFail();
        $template = $trabalho->programa?->templateLatex;

        // 1. zip → projeto (prefere o .tex principal do template)
        try {
            $projeto = $zipService->extrair(
                Storage::disk('local')->path($submissao->zip_path),
                $tmp,
                $template?->tex_principal,
            );
        } catch (ProjetoLatexInvalidoException $e) {
            $submissao->update([
                'status' => 'nao_ok',
                'log_resumido' => $e->getMessage(),
                'conformidade' => [],
            ]);

            return;
        }

        // 2. elementos vitais com o mapa do template
        $fonte = $projeto->fonteExpandido();
        $elementos = $extrator->extrair($fonte, $template?->mapa_elementos);

        // 3. idioma
        $idioma = self::detectarIdioma($fonte);

        // 4. conformidade com o cadastro, contra a banca do pedido (proposta)
        $itens = $conformidade->conferir($elementos, $trabalho, $submissao->banca, $submissao->tipo_documento, $idioma);

        $avisos = [];
        $classe = $projeto->classeDocumento();
        if ($template?->classe_documento && $classe !== null && strcasecmp($classe, $template->classe_documento) !== 0) {
            // D2: só aviso, não muda o veredito
            $avisos[] = "O projeto não usa a classe do template do programa ({$template->classe_documento}); usa {$classe}.";
        }

        $motor = $this->motor($extrator, $fonte, $template?->motor);

        $submissao->update([
            'tex_principal' => $projeto->texPrincipal,
            'banca_composicao_hash' => TrabalhoSubmissaoLatex::impressaoBanca($submissao->banca),
            'idioma' => $idioma,
            'motor' => $motor,
            'elementos' => $elementos,
            'conformidade' => $itens,
        ]);

        // 5. compila a versão da banca (com numeração; sem, se o lineno falhar)
        [$compilou, $numeracao, $log, $avisosCompilacao, $pdf, $bbl] = $this->compilar($compilador, $projeto, $motor, $submissao);
        if ($compilou && ! $numeracao) {
            $avisos[] = self::AVISO_SEM_NUMERACAO;
        }
        $avisos = array_values(array_unique([...$avisos, ...$avisosCompilacao]));

        // 6. veredito
        $status = $compilou && ! $conformidade->bloqueia($itens) ? 'ok' : 'nao_ok';

        $submissao->update([
            'status' => $status,
            'numeracao_linhas' => $compilou && $numeracao,
            'pdf_banca_path' => $pdf,
            'bbl_path' => $bbl,
            'log_resumido' => mb_substr($log, 0, self::MAX_LOG),
            'avisos' => $avisos,
            'refs_status' => 'pendente',
        ]);

        // 7. o trabalho passa a refletir o que veio do .tex
        $this->atualizarTrabalho($trabalho, $elementos, $idioma);

        // 8. verificação das referências (Tarefa 10), só quando compilou
        $jobReferencias = 'App\\Jobs\\VerificarReferenciasSubmissaoJob';
        if ($compilou && class_exists($jobReferencias)) {
            $jobReferencias::dispatch($submissao->fresh());
        }
    }

    /**
     * @return array{0: bool, 1: bool, 2: string, 3: list<string>, 4: ?string, 5: ?string}
     *                                                                                     [compilou, numerada, log, avisos, pdf_banca_path, bbl_path]
     */
    private function compilar(CompiladorLatex $compilador, ProjetoLatex $projeto, string $motor, TrabalhoSubmissaoLatex $submissao): array
    {
        /** @var list<ResultadoCompilacao> $resultados */
        $resultados = [];

        try {
            $resultados[] = $numerada = $compilador->compilar($projeto, $motor, true);
            $final = $numerada;
            $numeracao = true;

            if (! $numerada->sucesso) {
                $resultados[] = $simples = $compilador->compilar($projeto, $motor, false);
                $final = $simples;
                $numeracao = false;
            }

            if (! $final->sucesso) {
                return [false, false, $final->logResumido, $final->avisos, null, null];
            }

            $dir = $submissao->diretorioArquivos();
            $pdf = "{$dir}/banca.pdf";
            Storage::disk('local')->put($pdf, (string) file_get_contents($final->pdfPath));

            $bbl = null;
            if ($final->bblPath !== null && is_file($final->bblPath)) {
                $bbl = "{$dir}/referencias.bbl";
                Storage::disk('local')->put($bbl, (string) file_get_contents($final->bblPath));
            }

            return [true, $numeracao, $final->logResumido, $final->avisos, $pdf, $bbl];
        } finally {
            // o diretório de saída é de quem chama: limpa em sucesso e em falha
            foreach ($resultados as $resultado) {
                if ($resultado->diretorioSaida !== null) {
                    File::deleteDirectory($resultado->diretorioSaida);
                }
            }
        }
    }

    /** Motor do projeto do discente quando ele pede outro; senão o do template; senão pdflatex. */
    private function motor(ExtratorElementosLatexService $extrator, string $fonte, ?string $motorTemplate): string
    {
        $detectado = $extrator->detectarMotor($fonte);
        if ($detectado !== 'pdflatex') {
            return $detectado;
        }

        return in_array($motorTemplate, ['pdflatex', 'xelatex', 'lualatex'], true) ? $motorTemplate : 'pdflatex';
    }

    /** `diss=en`/`tese=en` nas opções da classe, ou a última língua do babel igual a english → en. */
    public static function detectarIdioma(string $fonte): string
    {
        $fonte = FonteLatex::removerComentarios(FonteLatex::paraUtf8($fonte));

        if (preg_match('/\\\\documentclass\s*\[([^\]]*)\]/', $fonte, $m)) {
            $opcoes = array_map(fn ($o) => preg_replace('/\s+/', '', $o), explode(',', $m[1]));
            if (array_intersect($opcoes, ['diss=en', 'tese=en']) !== []) {
                return 'en';
            }
        }

        if (preg_match_all('/\\\\usepackage\s*\[([^\]]*)\]\s*\{[^}]*\bbabel\b[^}]*\}/', $fonte, $m)) {
            $linguas = array_values(array_filter(array_map('trim', explode(',', end($m[1])))));
            $ultima = strtolower((string) end($linguas));
            if ($ultima === 'english') {
                return 'en';
            }
        }

        return 'pt';
    }

    private function atualizarTrabalho($trabalho, array $elementos, string $idioma): void
    {
        $limpo = function (?string ...$codigos) use ($elementos): ?string {
            foreach ($codigos as $codigo) {
                $valor = $elementos[$codigo] ?? null;
                if (is_array($valor)) {
                    $valor = implode('; ', $valor);
                }
                $valor = is_string($valor) ? NomePessoa::limparLatex($valor) : '';
                if ($valor !== '') {
                    return $valor;
                }
            }

            return null;
        };

        $en = $idioma === 'en';
        $dados = array_filter([
            'titulo' => $en ? $limpo(ElementosVitais::TITULO_EN, ElementosVitais::TITULO_PT) : $limpo(ElementosVitais::TITULO_PT),
            'resumo' => $en ? $limpo(ElementosVitais::ABSTRACT_EN, ElementosVitais::RESUMO_PT) : $limpo(ElementosVitais::RESUMO_PT),
            'palavras_chave' => $en
                ? $limpo(ElementosVitais::PALAVRAS_CHAVE_EN, ElementosVitais::PALAVRAS_CHAVE_PT)
                : $limpo(ElementosVitais::PALAVRAS_CHAVE_PT),
        ], fn ($v) => $v !== null);

        // titulo e palavras_chave são varchar(255)
        foreach (['titulo', 'palavras_chave'] as $campo) {
            if (isset($dados[$campo])) {
                $dados[$campo] = mb_substr($dados[$campo], 0, 255);
            }
        }

        if ($dados !== []) {
            $trabalho->update($dados);
        }
    }

    /** O detalhe vai para o log do servidor (report); o discente vê só a mensagem genérica. */
    private function marcarErro(?Throwable $e): void
    {
        TrabalhoSubmissaoLatex::whereKey($this->submissao->id)->update([
            'status' => 'erro',
            'log_resumido' => self::MENSAGEM_ERRO,
        ]);
    }
}
