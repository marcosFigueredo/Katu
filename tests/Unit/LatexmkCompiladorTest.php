<?php

namespace Tests\Unit;

use App\Services\Latex\LatexmkCompilador;
use App\Services\Latex\ProjetoLatex;
use App\Services\Latex\ProjetoLatexZipService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class LatexmkCompiladorTest extends TestCase
{
    private function projeto(): ProjetoLatex
    {
        return new ProjetoLatex('/tmp/proj', 'main.tex');
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
        $cmd = (new LatexmkCompilador('latexmk'))->montarComando($this->projeto(), 'xelatex', false, '/tmp/out');

        $this->assertSame('latexmk', $cmd[0]);
        $this->assertContains('-pdfxe', $cmd);
        $this->assertContains('-no-shell-escape', $cmd);
        $this->assertContains('-halt-on-error', $cmd);
        $this->assertContains('-outdir=/tmp/out', $cmd);
        $this->assertSame('main.tex', end($cmd));
        $this->assertEmpty(array_filter($cmd, fn ($a) => str_starts_with($a, '-usepretex')));
    }

    public function test_comando_com_numeracao_inclui_lineno(): void
    {
        $cmd = (new LatexmkCompilador)->montarComando($this->projeto(), 'pdflatex', true, '/tmp/out');

        $this->assertContains('-pdf', $cmd);
        $this->assertStringContainsString('\AtBeginDocument{\RequirePackage{lineno}\linenumbers}', $this->pretex($cmd));
    }

    public function test_comando_com_apendice_inclui_input_no_fim(): void
    {
        $cmd = (new LatexmkCompilador)->montarComando($this->projeto(), 'lualatex', false, '/tmp/out', '/tmp/out/apendice.tex');

        $this->assertContains('-pdflua', $cmd);
        $this->assertStringContainsString('\AtEndDocument{\clearpage\input{/tmp/out/apendice.tex}}', $this->pretex($cmd));
    }

    public function test_resume_log_com_erro(): void
    {
        $log = "This is pdfTeX\n(./main.tex\nLaTeX Warning: Reference `x' undefined.\n"
            ."! Undefined control sequence.\n<recently read> \\foo\n\nl.12 \\foo\n\nmais ruido\n"
            ."Class abnt Warning: algo estranho.\n";

        $r = (new LatexmkCompilador)->montarResultado(false, null, null, $log);

        $this->assertFalse($r->sucesso);
        $this->assertStringContainsString('! Undefined control sequence.', $r->logResumido);
        $this->assertStringContainsString('l.12 \foo', $r->logResumido);
        $this->assertStringNotContainsString('mais ruido', $r->logResumido);
        $this->assertSame(['Class abnt Warning: algo estranho.'], $r->avisos);
    }

    #[Group('latex')]
    public function test_compila_template_real(): void
    {
        $saida = [];
        exec('latexmk -v 2>&1', $saida, $codigo);
        if ($codigo !== 0) {
            $this->markTestSkipped('latexmk indisponível: '.implode(' ', $saida));
        }

        $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexreal_'.uniqid();
        mkdir($destino, 0777, true);
        $zip = dirname(__DIR__, 2).'/docs/classppgmsb.zip';
        $projeto = (new ProjetoLatexZipService)->extrair($zip, $destino, 'dissertacao.tex');

        $r = (new LatexmkCompilador)->compilar($projeto, 'pdflatex', true);

        $this->assertTrue($r->sucesso, $r->logResumido);
        $this->assertNotNull($r->pdfPath);
        $this->assertFileExists($r->pdfPath);
    }
}
