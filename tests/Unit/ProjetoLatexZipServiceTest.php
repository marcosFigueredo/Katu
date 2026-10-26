<?php

namespace Tests\Unit;

use App\Exceptions\ProjetoLatexInvalidoException;
use App\Services\Latex\ProjetoLatexZipService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ProjetoLatexZipServiceTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'latexzip_'.uniqid();
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

    /** @param array<string,string> $arquivos */
    private function zip(array $arquivos): string
    {
        $path = $this->base.DIRECTORY_SEPARATOR.uniqid('p').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($arquivos as $nome => $conteudo) {
            $zip->addFromString($nome, $conteudo);
        }
        $zip->close();

        return $path;
    }

    private function extrair(array $arquivos, ?string $pref = null)
    {
        $destino = $this->base.DIRECTORY_SEPARATOR.uniqid('d');

        return (new ProjetoLatexZipService)->extrair($this->zip($arquivos), $destino, $pref);
    }

    private const DOC = "\documentclass{article}\n\begin{document}\nOi\n\end{document}\n";

    public function test_extrai_projeto_em_subpasta_e_acha_principal(): void
    {
        $p = $this->extrair(['proj/main.tex' => self::DOC, 'proj/cap/intro.tex' => 'x']);
        $this->assertSame('main.tex', $p->texPrincipal);
        $this->assertStringEndsWith('proj', $p->raiz);
    }

    public function test_rejeita_entrada_com_ponto_ponto(): void
    {
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->extrair(['main.tex' => self::DOC, '../evil.tex' => 'x']);
    }

    public function test_rejeita_ponto_ponto_com_barra_invertida(): void
    {
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->extrair(['main.tex' => self::DOC, 'a\..\..\evil.tex' => 'x']);
    }

    public function test_rejeita_caminho_absoluto(): void
    {
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->extrair(['main.tex' => self::DOC, '/etc/evil.tex' => 'x']);
    }

    public function test_rejeita_caminho_absoluto_windows(): void
    {
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->extrair(['main.tex' => self::DOC, 'C:\evil.tex' => 'x']);
    }

    public function test_rejeita_zip_sem_documentclass(): void
    {
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->expectExceptionMessage('Nenhum arquivo .tex principal');
        $this->extrair(['a.tex' => 'so texto']);
    }

    public function test_ambiguidade_resolvida_por_nome_preferido(): void
    {
        $p = $this->extrair(['a.tex' => self::DOC, 'dissertacao.tex' => self::DOC], 'dissertacao.tex');
        $this->assertSame('dissertacao.tex', $p->texPrincipal);
    }

    public function test_ambiguidade_sem_preferencia_gera_erro(): void
    {
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->expectExceptionMessage('mais de um');
        $this->extrair(['a.tex' => self::DOC, 'b.tex' => self::DOC]);
    }

    public function test_linhas_comentadas_nao_contam(): void
    {
        $p = $this->extrair([
            'a.tex' => "% \documentclass{x}\n% \begin{document}\n",
            'b.tex' => self::DOC,
        ]);
        $this->assertSame('b.tex', $p->texPrincipal);
    }

    public function test_fonte_expandido_inclui_input_e_include(): void
    {
        $main = "\documentclass{article}\n\begin{document}\n\input{cap/intro}\n\include{cap/fim.tex}\n\input{nao_existe}\n\end{document}\n";
        $p = $this->extrair(['main.tex' => $main, 'cap/intro.tex' => 'TEXTO-INTRO \input{sub}', 'cap/sub.tex' => 'SUB', 'cap/fim.tex' => 'TEXTO-FIM']);
        $f = $p->fonteExpandido();
        $this->assertStringContainsString('TEXTO-INTRO', $f);
        $this->assertStringContainsString('TEXTO-FIM', $f);
        $this->assertStringNotContainsString('\include{', $f);
        $this->assertStringNotContainsString('nao_existe', $f);
    }

    public function test_input_recursivo_infinito_nao_trava(): void
    {
        $main = "\documentclass{article}\n\begin{document}\n\input{a}\n\end{document}\n";
        $p = $this->extrair(['main.tex' => $main, 'a.tex' => 'A\input{a}']);
        $this->assertStringContainsString('A', $p->fonteExpandido());
    }

    public function test_ignora_macosx_e_ds_store(): void
    {
        $p = $this->extrair([
            'main.tex' => self::DOC,
            '__MACOSX/._main.tex' => self::DOC,
            '.DS_Store' => 'x',
            'desktop.ini' => 'x',
        ]);
        $this->assertSame('main.tex', $p->texPrincipal);
        $this->assertFileDoesNotExist($p->raiz.'/.DS_Store');
        $this->assertFileDoesNotExist($p->raiz.'/desktop.ini');
        $this->assertDirectoryDoesNotExist(dirname($p->raiz).'/__MACOSX');
    }

    public function test_classe_documento_lida_do_principal(): void
    {
        $p = $this->extrair(['main.tex' => "\documentclass[diss]{classPPGMSB}\n\begin{document}\n\end{document}\n"]);
        $this->assertSame('classPPGMSB', $p->classeDocumento());
    }

    public function test_rejeita_zip_com_arquivos_demais(): void
    {
        $arquivos = ['main.tex' => self::DOC];
        for ($i = 0; $i < 2001; $i++) {
            $arquivos["f$i.txt"] = 'x';
        }
        $this->expectException(ProjetoLatexInvalidoException::class);
        $this->extrair($arquivos);
    }

    public function test_rejeita_arquivo_que_nao_e_zip(): void
    {
        $f = $this->base.'/x.zip';
        file_put_contents($f, 'nao sou zip');
        $this->expectException(ProjetoLatexInvalidoException::class);
        (new ProjetoLatexZipService)->extrair($f, $this->base.'/dest');
    }

    public function test_zip_real_do_modelo_extrai_com_dissertacao_preferida(): void
    {
        $zip = dirname(__DIR__, 2).'/docs/classppgmsb.zip';
        if (! is_file($zip)) {
            $this->markTestSkipped('docs/classppgmsb.zip ausente');
        }
        $p = (new ProjetoLatexZipService)->extrair($zip, $this->base.'/real', 'dissertacao.tex');
        $this->assertSame('dissertacao.tex', $p->texPrincipal);
        $this->assertSame('classPPGMSB', $p->classeDocumento());
        $this->assertNotSame('', $p->fonteExpandido());
    }
}
