<?php

namespace Tests\Unit;

use App\Services\Latex\FonteLatex;
use PHPUnit\Framework\TestCase;

class FonteLatexTest extends TestCase
{
    public function test_remover_comentarios(): void
    {
        $this->assertSame("a \nb\\% c \nd\\\\", FonteLatex::removerComentarios("a % x\nb\\% c % y\nd\\\\% z"));
        $this->assertSame("\r\nx", FonteLatex::removerComentarios("% so comentario\r\nx"));
    }

    public function test_remover_comentarios_em_linha_de_1mb(): void
    {
        $linha = str_repeat('ab\\%cd ', 180000);
        $this->assertSame($linha."\nfim", FonteLatex::removerComentarios($linha."% comentário\nfim"));
    }

    public function test_normalizar_texto_longo_e_utf8_invalido(): void
    {
        $this->assertSame(trim(str_repeat('x ', 600000)), FonteLatex::normalizar(str_repeat("x \n\t ", 600000)));
        $this->assertSame("sa\xFAde ok", FonteLatex::normalizar("sa\xFAde \n ok"));
    }

    public function test_para_utf8(): void
    {
        $this->assertSame('João', FonteLatex::paraUtf8("Jo\xE3o"));
        $this->assertSame('João', FonteLatex::paraUtf8('João'));
    }

    public function test_argumento_com_chaves_balanceadas_e_escapadas(): void
    {
        $f = new FonteLatex("\\X[op{]}]{a {b} \\} c} \\X{sem fim");
        $this->assertSame(['a {b} \\} c'], $f->argumentosDeComandos(fn ($n) => $n === 'X'));
    }
}
