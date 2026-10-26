<?php

namespace Tests\Unit;

use App\Support\NomePessoa;
use PHPUnit\Framework\TestCase;

class NomePessoaTest extends TestCase
{
    public function test_ignora_titulos_acentos_e_inversao(): void
    {
        $this->assertTrue(NomePessoa::equivalentes('Profa. Dra. Ana Paula Menezes', 'ana paula menezes'));
        $this->assertTrue(NomePessoa::equivalentes('Gildásio Moreira', 'Gildasio Moreira'));
        $this->assertTrue(NomePessoa::equivalentes('Souza, Helena', 'Helena Souza'));
        $this->assertFalse(NomePessoa::equivalentes('Ana Paula', 'Ana Maria'));
    }

    public function test_converte_acentos_latex_para_unicode(): void
    {
        $this->assertSame('Gildásio', NomePessoa::limparLatex("Gild\'asio"));
        $this->assertSame('João Conceição', NomePessoa::limparLatex('Jo\~ao Concei\c{c}\~ao'));
        $this->assertSame('Aída', NomePessoa::limparLatex("A{\'\i}da"));
        $this->assertTrue(NomePessoa::equivalentes("Gild\'asio Moreira", 'Gildásio Moreira'));
    }

    public function test_remove_marcacao(): void
    {
        $this->assertSame('Modelagem em saúde', NomePessoa::limparLatex('{\em Modelagem}\, em sa\\\'ude'));
        $this->assertSame('a b', NomePessoa::simplificar('A,  B!'));
    }
}
