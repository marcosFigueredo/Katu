<?php

namespace Tests\Unit;

use App\Services\Latex\LatexmkCompilador;
use App\Services\Latex\ProjetoLatex;
use App\Services\Latex\ProjetoLatexZipService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class LatexmkCompiladorTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexcomp_'.uniqid();
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remover($this->base);
        parent::tearDown();
    }

    private function remover(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($p) ? $this->remover($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function projeto(string $principal = 'main.tex'): ProjetoLatex
    {
        return new ProjetoLatex('/tmp/proj', $principal);
    }

    /** Projeto em disco com binário inexistente: se o processo rodasse, o resultado seria "Falha ao executar". */
    private function projetoEmDisco(array $arquivos, string $principal = 'main.tex'): ProjetoLatex
    {
        foreach ($arquivos as $nome => $conteudo) {
            $caminho = $this->base.DIRECTORY_SEPARATOR.$nome;
            if (! is_dir(dirname($caminho))) {
                mkdir(dirname($caminho), 0777, true);
            }
            file_put_contents($caminho, $conteudo);
        }

        return new ProjetoLatex($this->base, $principal);
    }

    private function compiladorQueNaoDeveRodar(): LatexmkCompilador
    {
        return new LatexmkCompilador('binario-inexistente-xyz', 5, false, false);
    }

    /** @param list<string> $cmd */
    private function pretex(array $cmd): string
    {
        foreach ($cmd as $arg) {
            if (str_starts_with($arg, '-usepretex=')) {
                return $arg;
            }
        }

        return '';
    }

    public function test_comando_usa_no_shell_escape_e_motor(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, false, false))->montarComando($this->projeto(), 'xelatex', false, '/tmp/out');

        $this->assertSame('latexmk', $cmd[0]);
        $this->assertContains('-pdfxe', $cmd);
        $this->assertContains('-no-shell-escape', $cmd);
        $this->assertContains('-halt-on-error', $cmd);
        $this->assertContains('-outdir=/tmp/out', $cmd);
        $this->assertSame('./main.tex', end($cmd));
        $this->assertSame('', $this->pretex($cmd));
    }

    public function test_comando_contem_norc(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, false, false))->montarComando($this->projeto(), 'pdflatex', false, '/tmp/out');

        $this->assertContains('-norc', $cmd);
    }

    public function test_comando_com_numeracao_inclui_lineno(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, false, false))->montarComando($this->projeto(), 'pdflatex', true, '/tmp/out');

        $this->assertContains('-pdf', $cmd);
        $this->assertStringContainsString('\AtBeginDocument{\RequirePackage{lineno}\linenumbers}', $this->pretex($cmd));
    }

    public function test_comando_com_apendice_inclui_input_no_fim(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, false, false))->montarComando($this->projeto(), 'lualatex', false, '/tmp/out', '/tmp/out/apendice.tex');

        $this->assertContains('-pdflua', $cmd);
        $this->assertStringContainsString('\AtEndDocument{\clearpage\input{/tmp/out/apendice.tex}}', $this->pretex($cmd));
    }

    public function test_comando_no_linux_usa_timeout_externo_para_matar_o_grupo(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, false, true))->montarComando($this->projeto(), 'pdflatex', false, '/tmp/out');

        $this->assertSame(['timeout', '--kill-after=5', '180', 'latexmk'], array_slice($cmd, 0, 4));
        $this->assertSame('./main.tex', end($cmd));
    }

    public function test_comando_no_windows_nao_usa_timeout_externo_e_cita_pretex(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, true, false))->montarComando($this->projeto(), 'pdflatex', true, '/tmp/out');

        $this->assertSame('latexmk', $cmd[0]);
        $this->assertStringStartsWith('-usepretex="', $this->pretex($cmd));
    }

    public function test_resume_log_com_erro(): void
    {
        $log = "This is pdfTeX\n(./main.tex\nLaTeX Warning: Reference `x' undefined.\n"
            ."! Undefined control sequence.\n<recently read> \\foo\n\nl.12 \\foo\n\nmais ruido\n"
            ."Class abnt Warning: algo estranho.\n";

        $r = (new LatexmkCompilador)->montarResultado(null, null, $log);

        $this->assertFalse($r->sucesso);
        $this->assertStringContainsString('! Undefined control sequence.', $r->logResumido);
        $this->assertStringContainsString('l.12 \foo', $r->logResumido);
        $this->assertStringNotContainsString('mais ruido', $r->logResumido);
        $this->assertSame(['Class abnt Warning: algo estranho.'], $r->avisos);
    }

    public function test_pdf_antigo_com_erro_fatal_no_log_nao_e_sucesso(): void
    {
        $pdf = $this->base.DIRECTORY_SEPARATOR.'a.pdf';
        file_put_contents($pdf, '%PDF-1.4');

        $r = (new LatexmkCompilador)->montarResultado($pdf, null, "texto\n! Undefined control sequence.\nl.3 \\x\n", $this->base);

        $this->assertFalse($r->sucesso);
    }

    public function test_log_limpo_com_pdf_e_sucesso_mesmo_com_aviso_de_bibliografia(): void
    {
        $pdf = $this->base.DIRECTORY_SEPARATOR.'a.pdf';
        file_put_contents($pdf, '%PDF-1.4');

        $r = (new LatexmkCompilador)->montarResultado($pdf, null, "Warning--I didn't find a database entry for \"X\"\n", $this->base);

        $this->assertTrue($r->sucesso);
        $this->assertSame($this->base, $r->diretorioSaida);
    }

    public function test_sem_pdf_nao_e_sucesso(): void
    {
        $r = (new LatexmkCompilador)->montarResultado(null, null, "log limpo\n");

        $this->assertFalse($r->sucesso);
    }

    public function test_recusa_projeto_com_latexmkrc_sem_executar_o_processo(): void
    {
        foreach (['latexmkrc', '.latexmkrc', 'sub/LatexMkRC'] as $nome) {
            $projeto = $this->projetoEmDisco(['main.tex' => '\documentclass{article}', $nome => 'system("x");']);

            $r = $this->compiladorQueNaoDeveRodar()->compilar($projeto, 'pdflatex', false);

            $this->assertFalse($r->sucesso, $nome);
            $this->assertStringContainsString('configuração do latexmk', $r->logResumido);
            $this->assertStringNotContainsString('Falha ao executar', $r->logResumido);
            $this->remover($this->base);
            mkdir($this->base, 0777, true);
        }
    }

    #[DataProvider('principaisInvalidos')]
    public function test_recusa_arquivo_principal_invalido(string $principal): void
    {
        $projeto = $this->projetoEmDisco(['main.tex' => 'x'], $principal);

        $r = $this->compiladorQueNaoDeveRodar()->compilar($projeto, 'pdflatex', false);

        $this->assertFalse($r->sucesso);
        $this->assertStringContainsString('Nome do arquivo principal inválido', $r->logResumido);
    }

    public static function principaisInvalidos(): array
    {
        return [
            'injecao de opcao' => ['-pdflatex=x.tex'],
            'espaco' => ['a b.tex'],
            'especiais' => ['a$b.tex'],
            'ponto-ponto' => ['../x.tex'],
            'absoluto' => ['/etc/x.tex'],
        ];
    }

    public function test_principal_valido_aparece_como_relativo_no_comando(): void
    {
        $cmd = (new LatexmkCompilador('latexmk', 180, false, false))->montarComando($this->projeto('dissertacao.tex'), 'pdflatex', false, '/tmp/out');

        $this->assertSame('./dissertacao.tex', end($cmd));
        $this->assertContains('-jobname=dissertacao', $cmd);
    }

    #[DataProvider('leiturasPerigosas')]
    public function test_recusa_leitura_fora_do_projeto(string $fonte, string $comando): void
    {
        $projeto = $this->projetoEmDisco(['main.tex' => $fonte]);

        $r = $this->compiladorQueNaoDeveRodar()->compilar($projeto, 'pdflatex', false);

        $this->assertFalse($r->sucesso);
        $this->assertStringContainsString('main.tex', $r->logResumido);
        $this->assertStringContainsString($comando, $r->logResumido);
        $this->assertStringNotContainsString('Falha ao executar', $r->logResumido);
    }

    public static function leiturasPerigosas(): array
    {
        return [
            'input absoluto windows' => ['\input{C:/x}', '\input'],
            'input absoluto unix' => ['\input{/etc/passwd}', '\input'],
            'verbatiminput com ..' => ['\verbatiminput{../../segredo.txt}', '\verbatiminput'],
            'includegraphics com opcoes' => ['\includegraphics[width=3cm]{/etc/passwd}', '\includegraphics'],
            'input primitivo' => ['\input /etc/passwd', '\input'],
            'openin' => ['\openin5=C:/Windows/win.ini', '\openin'],
            'bibliography lista' => ['\bibliography{ok,/etc/passwd}', '\bibliography'],
            'usepackage com caminho' => ['\usepackage{../evil}', '\usepackage'],
            'import' => ['\import{/etc/}{passwd}', '\import'],
            'input absoluto entre aspas' => ['\input{"C:/x/.env"}', '\input'],
            'input com .. entre aspas' => ['\input{"../segredo"}', '\input'],
            'openin entre aspas' => ['\openin5="C:/x/.env"', '\openin'],
            'openin com espacos e aspas' => ['\openin5 = "/etc/passwd"', '\openin'],
            'em sty' => ['\input{~/x}', '\input'],
        ];
    }

    public function test_varredura_olha_arquivos_de_apoio_e_ignora_comentarios_e_caminhos_relativos(): void
    {
        $projeto = $this->projetoEmDisco([
            'main.tex' => "\\input{capitulos/um}\n\\input{\"capitulos/intro\"}\n\\includegraphics{figuras/a.png}\n% \\input{/etc/passwd}\n\\bibliography{refs}",
            'capitulos/um.tex' => 'texto',
        ]);

        // binário inexistente: passou da varredura e tentou executar o processo
        $r = $this->compiladorQueNaoDeveRodar()->compilar($projeto, 'pdflatex', false);
        $this->assertStringNotContainsString('fora do projeto', $r->logResumido);

        $projeto2 = $this->projetoEmDisco(['capitulos/dois.tex' => '\InputIfFileExists{/etc/hosts}{}{}']);
        $r2 = $this->compiladorQueNaoDeveRodar()->compilar($projeto2, 'pdflatex', false);
        $this->assertStringContainsString('capitulos/dois.tex', $r2->logResumido);
    }

    #[Group('latex')]
    public function test_compila_template_real(): void
    {
        $saida = [];
        exec('latexmk -v 2>&1', $saida, $codigo);
        if ($codigo !== 0) {
            $this->markTestSkipped('latexmk indisponível: '.implode(' ', $saida));
        }

        $destino = $this->base.DIRECTORY_SEPARATOR.'real';
        mkdir($destino, 0777, true);
        $zip = dirname(__DIR__, 2).'/docs/classppgmsb.zip';
        $projeto = (new ProjetoLatexZipService)->extrair($zip, $destino, 'dissertacao.tex');

        $r = (new LatexmkCompilador)->compilar($projeto, 'pdflatex', true);

        try {
            $this->assertTrue($r->sucesso, $r->logResumido);
            $this->assertNotNull($r->pdfPath);
            $this->assertFileExists($r->pdfPath);
            $this->assertNotNull($r->diretorioSaida);
        } finally {
            if ($r->diretorioSaida) {
                $this->remover($r->diretorioSaida);
            }
        }
    }
}
