<?php

namespace Tests\Unit;

use App\Services\Latex\ElementosVitais as E;
use App\Services\Latex\ExtratorElementosLatexService;
use App\Services\Latex\ProjetoLatexZipService;
use PHPUnit\Framework\TestCase;

class ExtratorElementosLatexServiceTest extends TestCase
{
    private static ?string $fontePpgmsb = null;

    private ExtratorElementosLatexService $extrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extrator = new ExtratorElementosLatexService;
    }

    private function fontePpgmsb(): string
    {
        if (self::$fontePpgmsb === null) {
            $raiz = dirname(__DIR__, 2);
            $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'extrator_'.uniqid();
            mkdir($tmp, 0777, true);
            try {
                $projeto = (new ProjetoLatexZipService)->extrair($raiz.'/docs/classppgmsb.zip', $tmp, 'dissertacao.tex');
                self::$fontePpgmsb = $projeto->fonteExpandido();
            } finally {
                $this->remover($tmp);
            }
        }

        return self::$fontePpgmsb;
    }

    private function fixture(string $nome): string
    {
        return file_get_contents(dirname(__DIR__)."/fixtures/latex/{$nome}/main.tex");
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

    public function test_extrai_ppgmsb(): void
    {
        $r = $this->extrator->extrair($this->fontePpgmsb());

        $this->assertStringStartsWith('Este é um título', $r[E::TITULO_PT]);
        $this->assertStringStartsWith('This is a dissertation', $r[E::TITULO_EN]);
        $this->assertSame('modelagem; simulação; otimização', $r[E::PALAVRAS_CHAVE_PT]);
        $this->assertSame('modeling; simulation; optimization', $r[E::PALAVRAS_CHAVE_EN]);
        $this->assertStringStartsWith('A segmentação para a caligrafia', $r[E::RESUMO_PT]);
        $this->assertStringStartsWith('With the marvelous', $r[E::ABSTRACT_EN]);
        $this->assertSame('Gildásio Moreira de Souza', $r[E::AUTOR]);
        $this->assertSame('Antonio Guimarães Rosa', $r[E::ORIENTADOR]);
        $this->assertSame(['Nome do Coorientador A', 'Nome do Coorientador B', 'Nome do Coorientador C'], $r[E::COORIENTADORES]);
        $this->assertContains('Profa. Helena Souza Lima', $r[E::MEMBROS_BANCA]);
        $this->assertCount(5, $r[E::MEMBROS_BANCA]);
        $this->assertSame('01/01/2023', $r[E::DATA_DEFESA]);
        $this->assertSame('Alagoinhas/BA', $r[E::LOCAL_DEFESA]);
    }

    public function test_extrai_abntex2(): void
    {
        $r = $this->extrator->extrair($this->fixture('abntex2'));

        $this->assertSame('Modelagem de sistemas biológicos com abnTeX2', $r[E::TITULO_PT]);
        $this->assertSame('Este trabalho apresenta um modelo de simulação para sistemas biológicos.', $r[E::RESUMO_PT]);
        $this->assertStringContainsString('This work presents a simulation model for biological systems.', $r[E::ABSTRACT_EN]);
        $this->assertStringNotContainsString('Keywords', $r[E::ABSTRACT_EN]);
        $this->assertSame('modelagem. simulação. biossistemas.', $r[E::PALAVRAS_CHAVE_PT]);
        $this->assertSame('modeling. simulation. biosystems.', $r[E::PALAVRAS_CHAVE_EN]);
        $this->assertSame('Maria da Silva Santos', $r[E::AUTOR]);
        $this->assertSame('Prof. Dr. João Pereira Costa', $r[E::ORIENTADOR]);
        $this->assertSame(['Profa. Dra. Ana Lúcia Ramos'], $r[E::COORIENTADORES]);
        $this->assertNull($r[E::MEMBROS_BANCA]);
    }

    public function test_extrai_article_sem_banca(): void
    {
        $r = $this->extrator->extrair($this->fixture('article'));

        $this->assertNull($r[E::MEMBROS_BANCA]);
        $this->assertNull($r[E::ORIENTADOR]);
        $this->assertNull($r[E::COORIENTADORES]);
        $this->assertNull($r[E::RESUMO_PT]);
        $this->assertSame('A plain article about simulation', $r[E::TITULO_EN]);
        $this->assertSame('We study simulation of plain systems.', $r[E::ABSTRACT_EN]);
        $this->assertSame('John Doe', $r[E::AUTOR]);
        $this->assertSame(E::todos(), array_keys($r));
    }

    public function test_descobrir_mapa_ppgmsb(): void
    {
        $mapa = $this->extrator->descobrirMapa($this->fontePpgmsb());

        $this->assertSame(['tipo' => 'ambiente', 'nome' => 'resumo'], $mapa['resumo_pt']);
        $this->assertSame('newAbstract', $mapa['abstract_en']['nome']);
        $this->assertSame(['tipo' => 'padrao_comando', 'nome' => 'Membro[A-Z]Nome'], $mapa['membros_banca']);
        $this->assertSame(['tipo' => 'comando', 'nome' => 'TituloBR'], $mapa['titulo_pt']);
    }

    public function test_descobrir_mapa_abntex2_e_article(): void
    {
        $mapa = $this->extrator->descobrirMapa($this->fixture('abntex2'));
        $this->assertSame(['tipo' => 'ambiente_opcao', 'nome' => 'resumo', 'opcao' => 'Abstract'], $mapa['abstract_en']);
        $this->assertArrayNotHasKey('membros_banca', $mapa);

        $mapa = $this->extrator->descobrirMapa($this->fixture('article'));
        $this->assertSame(['tipo' => 'ambiente', 'nome' => 'abstract'], $mapa['abstract_en']);
        $this->assertArrayNotHasKey('orientador', $mapa);
    }

    public function test_ignora_comentarios(): void
    {
        $fonte = "% \\TituloBR{falso}\n\\TituloBR{verdadeiro} % \\TituloEN{falso}\n50\\% de \\NomeAutor{Fulano}";
        $r = $this->extrator->extrair($fonte);

        $this->assertSame('verdadeiro', $r[E::TITULO_PT]);
        $this->assertNull($r[E::TITULO_EN]);
        $this->assertSame('Fulano', $r[E::AUTOR]);
    }

    public function test_ignora_definicoes_de_comando(): void
    {
        $fonte = "\\newcommand{\\TituloBR}[1]{\\def\\@tituloBR{#1}}\n\\newcommand*\\NomeAutor[1]{x}\n\\def\\OrientadorNome#1{#1}\n\\TituloBR{Real}";
        $r = $this->extrator->extrair($fonte);

        $this->assertSame('Real', $r[E::TITULO_PT]);
        $this->assertNull($r[E::AUTOR]);
        $this->assertNull($r[E::ORIENTADOR]);
    }

    public function test_le_argumento_com_chaves_aninhadas(): void
    {
        $r = $this->extrator->extrair("\\TituloBR[curto]{Um {\\em título} com chaves}");

        $this->assertSame('Um {\\em título} com chaves', $r[E::TITULO_PT]);
    }

    public function test_secao_como_reserva(): void
    {
        $fonte = "\\chapter*{Resumo}\nTexto do resumo.\n\nPalavras-chave: a; b.\n\\chapter*{Abstract}\nAbstract text.\n\\chapter{Introdução}\nx";
        $r = $this->extrator->extrair($fonte);

        $this->assertSame('Texto do resumo.', $r[E::RESUMO_PT]);
        $this->assertSame('a; b.', $r[E::PALAVRAS_CHAVE_PT]);
        $this->assertSame('Abstract text.', $r[E::ABSTRACT_EN]);
        $this->assertSame(['tipo' => 'secao', 'nome' => 'Resumo'], $this->extrator->descobrirMapa($fonte)['resumo_pt']);
    }

    public function test_mapa_com_codigo_inexistente_cai_nas_regras(): void
    {
        $mapa = [
            'titulo_pt' => ['tipo' => 'comando', 'nome' => 'ComandoQueNaoExiste'],
            'codigo_que_nao_existe' => ['tipo' => 'comando', 'nome' => 'TituloEN'],
            'autor' => ['tipo' => 'tipo_invalido', 'nome' => 'x'],
            'orientador' => 'lixo',
            'membros_banca' => ['tipo' => 'padrao_comando', 'nome' => '(((('],
        ];
        $r = $this->extrator->extrair($this->fontePpgmsb(), $mapa);

        $this->assertStringStartsWith('Este é um título', $r[E::TITULO_PT]);
        $this->assertSame('Gildásio Moreira de Souza', $r[E::AUTOR]);
        $this->assertSame('Antonio Guimarães Rosa', $r[E::ORIENTADOR]);
        $this->assertCount(5, $r[E::MEMBROS_BANCA]);
        $this->assertArrayNotHasKey('codigo_que_nao_existe', $r);
    }

    public function test_mapa_tem_prioridade_sobre_as_regras(): void
    {
        $fonte = "\\TituloBR{Pelas regras}\n\\MeuTitulo{Pelo mapa}\n\\BancaUm{Fulano}\n\\BancaDois{Beltrano}";
        $mapa = [
            'titulo_pt' => ['tipo' => 'comando', 'nome' => 'MeuTitulo'],
            'membros_banca' => ['tipo' => 'padrao_comando', 'nome' => 'Banca[A-Za-z]+'],
        ];
        $r = $this->extrator->extrair($fonte, $mapa);

        $this->assertSame('Pelo mapa', $r[E::TITULO_PT]);
        $this->assertSame(['Fulano', 'Beltrano'], $r[E::MEMBROS_BANCA]);
    }

    public function test_detectar_motor(): void
    {
        $this->assertSame('xelatex', $this->extrator->detectarMotor("% !TEX program = xelatex\n\\documentclass{article}"));
        $this->assertSame('lualatex', $this->extrator->detectarMotor("%!TeX TS-program = LuaLaTeX\n"));
        $this->assertSame('xelatex', $this->extrator->detectarMotor("\\documentclass{article}\n\\usepackage{fontspec}"));
        $this->assertSame('xelatex', $this->extrator->detectarMotor("\\usepackage[math-style=ISO]{unicode-math}"));
        $this->assertSame('pdflatex', $this->extrator->detectarMotor("% \\usepackage{fontspec}\n\\documentclass{article}"));
        $this->assertSame('pdflatex', $this->extrator->detectarMotor('\\documentclass{article}'));
        $this->assertSame('pdflatex', $this->extrator->detectarMotor($this->fontePpgmsb()));
    }
}
