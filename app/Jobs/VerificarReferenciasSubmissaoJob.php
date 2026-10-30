<?php

namespace App\Jobs;

use App\Models\SubmissaoReferencia;
use App\Models\TrabalhoSubmissaoLatex;
use App\Services\Latex\CompiladorLatex;
use App\Services\Latex\ProjetoLatexZipService;
use App\Services\Latex\VerificacaoReferenciasService;
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
 * Verifica as referências citadas (Crossref/OpenAlex), calcula a atualidade e a produção do
 * orientador e gera a versão de conferência do discente, com o apêndice "Relatório de
 * verificação das referências". O resultado nunca vai à Secretaria (regra 8).
 */
class VerificarReferenciasSubmissaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const AVISO_APENDICE = 'O apêndice de verificação das referências não pôde ser compilado; a versão de conferência é a mesma da banca.';

    public int $tries = 1;

    /** Consultas externas (orçamento de 420 s no serviço, com cache) e uma compilação de até 180 s. */
    public int $timeout = 900;

    public function __construct(public TrabalhoSubmissaoLatex $submissao) {}

    public function handle(
        VerificacaoReferenciasService $verificacao,
        ProjetoLatexZipService $zipService,
        CompiladorLatex $compilador,
    ): void {
        $submissao = $this->submissao->fresh();
        if ($submissao === null) {
            return;
        }

        $submissao->update(['refs_status' => 'processando']);

        $verificou = true;
        try {
            $verificacao->verificar($submissao);
        } catch (Throwable $e) {
            report($e);
            $this->marcarErro();
            $verificou = false;
        }

        // mesmo sem a verificação o discente recebe a conferência (versão da banca, com aviso)
        $this->gerarConferencia($submissao->fresh(), $zipService, $compilador, $verificou);
    }

    public function failed(?Throwable $e): void
    {
        if ($e !== null) {
            report($e);
        }
        $this->marcarErro();
    }

    private function marcarErro(): void
    {
        TrabalhoSubmissaoLatex::whereKey($this->submissao->id)->update(['refs_status' => 'erro']);
    }

    /** Compila o mesmo projeto com o apêndice; sem sucesso, a conferência é a versão da banca, com aviso. */
    private function gerarConferencia(
        TrabalhoSubmissaoLatex $submissao,
        ProjetoLatexZipService $zipService,
        CompiladorLatex $compilador,
        bool $comApendice = true,
    ): void {
        $destino = "{$submissao->diretorioArquivos()}/conferencia.pdf";
        $disco = Storage::disk('local');
        // diretório novo e exclusivo: o serviço do zip apaga o conteúdo do destino em caso de falha
        $tmp = storage_path('app/private/tmp/conferencia-latex-'.$submissao->id.'-'.Str::uuid());
        $resultado = null;
        $gerou = false;

        try {
            if (! $comApendice) {
                throw new \RuntimeException('Sem verificação: a conferência cai para a versão da banca.');
            }
            File::ensureDirectoryExists($tmp);
            $projeto = $zipService->extrair($disco->path($submissao->zip_path), $tmp, $submissao->tex_principal);
            $template = $submissao->trabalhoAcademico()->with('programa.templateLatex')->first()?->programa?->templateLatex;
            ProcessarSubmissaoLatexJob::completarComArquivosDoTemplate($projeto, $template, $zipService);
            $apendice = $this->montarApendice($submissao);

            $resultado = $compilador->compilar(
                $projeto,
                $submissao->motor ?: 'pdflatex',
                (bool) $submissao->numeracao_linhas,
                $apendice,
            );
            if ($resultado->sucesso && $resultado->pdfPath !== null && is_file($resultado->pdfPath)) {
                $disco->put($destino, (string) file_get_contents($resultado->pdfPath));
                $gerou = true;
            }
        } catch (Throwable $e) {
            if ($comApendice) {
                report($e);
            }
        } finally {
            // o diretório de saída é de quem chama: limpa em sucesso e em falha
            if ($resultado?->diretorioSaida !== null) {
                File::deleteDirectory($resultado->diretorioSaida);
            }
            File::deleteDirectory($tmp);
        }

        if ($gerou) {
            $submissao->update(['pdf_conferencia_path' => $destino]);

            return;
        }

        $avisos = (array) ($submissao->avisos ?? []);
        $avisos[] = self::AVISO_APENDICE;
        $dados = ['avisos' => array_values(array_unique($avisos))];
        if ($submissao->pdf_banca_path && $disco->exists($submissao->pdf_banca_path)) {
            $disco->copy($submissao->pdf_banca_path, $destino);
            $dados['pdf_conferencia_path'] = $destino;
        }
        $submissao->update($dados);
    }

    /** Apêndice .tex: tabelas curtas em sequência (quebram de página entre elas) e os totais. */
    public function montarApendice(TrabalhoSubmissaoLatex $submissao): string
    {
        $refs = $submissao->referencias()->orderBy('id')->get();
        $pct = $submissao->refs_recentes_pct;

        $tex = "\\clearpage\n\\begingroup\\small\n";
        $tex .= "\\noindent{\\Large\\bfseries Relatório de verificação das referências}\\par\\medskip\n";
        $tex .= '\\noindent Referências no .bbl: '.(int) $submissao->refs_total.'. ';
        $tex .= $pct !== null
            ? 'Com ano conhecido: '.(int) $submissao->refs_com_ano.'; publicadas nos 2 últimos anos: '
                .self::numero((float) $pct).'\\% ('.($submissao->refs_atualizadas ? 'acima' : 'não acima').' de 70\\%). '
            : 'Nenhuma referência com ano conhecido; atualidade não calculada. ';
        $tex .= 'Produção do orientador entre as referências: '.(int) $submissao->refs_orientador.".\\par\\smallskip\n";
        $tex .= "\\noindent Este relatório é conferência automática e orientativa: ``não localizada'' significa apenas que as bases consultadas não a encontraram; verifique cada caso.\\par\\medskip\n";

        foreach ($refs->chunk(8) as $grupo) {
            $tex .= "\\begingroup\\setlength{\\tabcolsep}{3pt}\n";
            $tex .= "\\begin{tabular}{|p{2.4cm}|p{2.6cm}|p{3.8cm}|p{1.1cm}|p{3.4cm}|}\\hline\n";
            $tex .= "\\textbf{Chave} & \\textbf{Situação} & \\textbf{DOI} & \\textbf{Ano} & \\textbf{Observação} \\\\ \\hline\n";
            foreach ($grupo as $r) {
                $tex .= implode(' & ', [
                    self::quebravel($r->chave ?? ''),
                    self::escapar(self::rotuloStatus($r->status)),
                    self::quebravel($r->doi_encontrado ?? $r->doi_tex ?? ''),
                    (string) ($r->anoConsiderado() ?? ''),
                    self::escapar(self::observacao($r)),
                ])." \\\\ \\hline\n";
            }
            $tex .= "\\end{tabular}\\endgroup\\par\\smallskip\n";
        }
        $tex .= "\\endgroup\n";

        return $tex;
    }

    public static function rotuloStatus(string $status): string
    {
        return match ($status) {
            'localizada' => 'localizada',
            'nao_localizada' => 'não localizada — verificar',
            default => 'não verificada',
        };
    }

    private static function observacao(SubmissaoReferencia $r): string
    {
        $obs = [];
        if ($r->status === 'nao_verificada') {
            $obs[] = 'falha na consulta; tentar de novo';
        }
        if ($r->divergencia_doi) {
            $obs[] = 'DOI diferente do .bbl ('.$r->doi_tex.')';
        }
        if ($r->divergencia_ano) {
            $obs[] = 'ano diferente do .bbl ('.$r->ano_tex.')';
        }
        if ($r->do_orientador) {
            $obs[] = 'produção do orientador';
        }

        return implode('; ', $obs);
    }

    private static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
    }

    /** Escapa os caracteres especiais do LaTeX. */
    public static function escapar(string $texto): string
    {
        return strtr($texto, [
            '\\' => '\\textbackslash{}',
            '{' => '\\{',
            '}' => '\\}',
            '$' => '\\$',
            '&' => '\\&',
            '#' => '\\#',
            '%' => '\\%',
            '_' => '\\_',
            '^' => '\\textasciicircum{}',
            '~' => '\\textasciitilde{}',
        ]);
    }

    /** Escapa e permite quebra de linha depois de "/", "." e "_" (DOIs e chaves longas). */
    private static function quebravel(string $texto): string
    {
        return strtr(self::escapar($texto), [
            '/' => '/\\allowbreak{}',
            '.' => '.\\allowbreak{}',
            '\\_' => '\\_\\allowbreak{}',
        ]);
    }
}
