<?php

namespace Database\Seeders;

use App\Models\DocumentoChecklist;
use App\Models\DocumentoChecklistItem;
use App\Models\Programa;
use Illuminate\Database\Seeder;

class DocumentoChecklistsSeeder extends Seeder
{
    public function run(): void
    {
        $programa = Programa::where('sigla', 'PPGMSB')->first();
        $programaId = $programa?->id;
        $this->criarChecklistQualificacao($programaId);
        $this->criarChecklistDefesa($programaId);
    }

    private function criarChecklistQualificacao(?int $programaId): void
    {
        $checklist = DocumentoChecklist::updateOrCreate(
            [
                'programa_id'    => $programaId,
                'tipo_documento' => 'qualificacao',
                'nivel'          => 'ambos',
                'versao'         => '1.0',
            ],
            [
                'nome'     => 'Checklist Formal de Qualificação',
                'ativo'    => true,
                'descricao' => 'Checklist formal para análise documental de exame de qualificação.',
            ]
        );

        $itens = [
            // --- Técnico ---
            [
                'grupo'          => 'tecnico',
                'nome'           => 'PDF pesquisável',
                'descricao'      => 'Verifica se o PDF possui texto pesquisável.',
                'impeditivo'     => true,
                'tipo_regra'     => 'pdf_pesquisavel',
                'termos_busca'   => null,
                'ordem_esperada' => 1,
            ],
            // --- Capa ---
            [
                'grupo'          => 'capa',
                'nome'           => 'Capa',
                'descricao'      => 'Verifica se a capa identifica o tipo de trabalho como Exame de Qualificação.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['EXAME DE QUALIFICAÇÃO', 'QUALIFICAÇÃO', 'Programa de Pós-Graduação'],
                'ordem_esperada' => 2,
            ],
            // --- Pré-textuais ---
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Resumo',
                'descricao'      => 'Verifica a presença do resumo em português.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Resumo'],
                'ordem_esperada' => 3,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Palavras-chave',
                'descricao'      => 'Verifica a presença de palavras-chave em português.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Palavras-chave', 'Palavras chave'],
                'ordem_esperada' => 4,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Abstract',
                'descricao'      => 'Verifica a presença do resumo em língua inglesa.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Abstract'],
                'ordem_esperada' => 5,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Keywords',
                'descricao'      => 'Verifica a presença de palavras-chave em inglês.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Keywords', 'Key words'],
                'ordem_esperada' => 6,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Sumário',
                'descricao'      => 'Verifica a presença do sumário.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Sumário', 'SUMÁRIO'],
                'ordem_esperada' => 7,
            ],
            // --- Textuais: Introdução e suas subseções obrigatórias ---
            [
                'grupo'          => 'textual',
                'nome'           => 'Introdução',
                'descricao'      => 'Verifica a presença do capítulo de introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Introdução', 'INTRODUÇÃO'],
                'ordem_esperada' => 8,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'O Problema',
                'descricao'      => 'Verifica a subseção referente ao problema de pesquisa dentro da Introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['O Problema', 'Problema de pesquisa', 'Problema'],
                'ordem_esperada' => 9,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Justificativa',
                'descricao'      => 'Verifica a subseção de justificativa dentro da Introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Justificativa'],
                'ordem_esperada' => 10,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Objetivos',
                'descricao'      => 'Verifica a subseção de objetivos (geral e específicos) dentro da Introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Objetivos', 'Objetivo Geral', 'Objetivos Específicos', 'Geral'],
                'ordem_esperada' => 11,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Aderência ao Programa',
                'descricao'      => 'Verifica a subseção obrigatória de aderência ao PPGMSB, que inclui área de concentração, linha de pesquisa e contribuição do trabalho.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Aderência ao Programa', 'Aderência Acadêmica'],
                'ordem_esperada' => 12,
            ],
            // Contribuições: não é seção formal no template — verificação complementar, não impeditiva
            [
                'grupo'           => 'textual',
                'nome'            => 'Contribuições',
                'descricao'       => 'Verifica menção às contribuições do trabalho. No template, este conteúdo está dentro da seção Aderência ao Programa.',
                'impeditivo'      => false,
                'status_se_ausente' => 'atencao',
                'tipo_regra'      => 'texto_flexivel',
                'termos_busca'    => ['Contribuições', 'Contribuição', 'Contribuições do trabalho', 'principal contribuição'],
                'ordem_esperada'  => 13,
            ],
            // --- Textuais: capítulos obrigatórios de qualificação ---
            [
                'grupo'          => 'textual',
                'nome'           => 'Estado da Arte ou Revisão de Literatura',
                'descricao'      => 'Verifica a presença de capítulo de revisão, fundamentação teórica ou estado da arte.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Estado da Arte', 'Revisão de Literatura', 'Fundamentação Teórica', 'Referencial Teórico', 'Revisão Bibliográfica'],
                'ordem_esperada' => 14,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Materiais e Métodos',
                'descricao'      => 'Verifica a presença do capítulo metodológico.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Materiais e Métodos', 'Materiais e métodos', 'Metodologia', 'Métodos'],
                'ordem_esperada' => 15,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Resultados Esperados',
                'descricao'      => 'Verifica a presença do capítulo de resultados esperados, obrigatório na qualificação.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Resultados Esperados', 'Resultados esperados', 'Resultados Preliminares'],
                'ordem_esperada' => 16,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Cronograma',
                'descricao'      => 'Verifica a presença do capítulo de cronograma, obrigatório na qualificação.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Cronograma'],
                'ordem_esperada' => 17,
            ],
            // --- Pós-textuais ---
            [
                'grupo'          => 'pos_textual',
                'nome'           => 'Referências',
                'descricao'      => 'Verifica a presença da seção de referências.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Referências', 'Referências Bibliográficas', 'Bibliografia'],
                'ordem_esperada' => 18,
            ],
            // --- Metadados ---
            [
                'grupo'          => 'metadados',
                'nome'           => 'Metadados do Documento',
                'descricao'      => 'Verifica a presença da seção de metadados ao final do documento, conforme template classPPGMSB.',
                'impeditivo'     => true,
                'tipo_regra'     => 'metadados_final',
                'termos_busca'   => ['Metadados do Documento', 'INFORMAÇÕES TÉCNICAS', 'Compilado em', 'classPPGMSB', 'PPGMSB-UNEB|classPPGMSB|CERTIFIED'],
                'ordem_esperada' => 19,
            ],
            // --- Técnico complementar ---
            [
                'grupo'           => 'tecnico',
                'nome'            => 'Classe LaTeX oficial',
                'descricao'       => 'Verifica se o documento indica uso da classe oficial do PPGMSB.',
                'impeditivo'      => false,
                'status_se_ausente' => 'atencao',
                'tipo_regra'      => 'classe_latex',
                'termos_busca'    => ['classPPGMSB', 'PPGMSB-UNEB|classPPGMSB|CERTIFIED'],
                'ordem_esperada'  => 20,
            ],
            [
                'grupo'           => 'imagens',
                'nome'            => 'Imagens com resolução mínima recomendada',
                'descricao'       => 'Verifica imagens rasterizadas abaixo de 300 DPI.',
                'impeditivo'      => false,
                'status_se_ausente' => 'nao_aplicavel',
                'tipo_regra'      => 'imagem_dpi',
                'termos_busca'    => null,
                'ordem_esperada'  => 21,
            ],
        ];

        $this->sincronizarItens($checklist, $itens);
    }

    private function criarChecklistDefesa(?int $programaId): void
    {
        $checklist = DocumentoChecklist::updateOrCreate(
            [
                'programa_id'    => $programaId,
                'tipo_documento' => 'defesa',
                'nivel'          => 'ambos',
                'versao'         => '1.0',
            ],
            [
                'nome'     => 'Checklist Formal de Defesa',
                'ativo'    => true,
                'descricao' => 'Checklist formal para análise documental de defesa de dissertação.',
            ]
        );

        $itens = [
            // --- Técnico ---
            [
                'grupo'          => 'tecnico',
                'nome'           => 'PDF pesquisável',
                'descricao'      => 'Verifica se o PDF possui texto pesquisável.',
                'impeditivo'     => true,
                'tipo_regra'     => 'pdf_pesquisavel',
                'termos_busca'   => null,
                'ordem_esperada' => 1,
            ],
            // --- Capa ---
            [
                'grupo'          => 'capa',
                'nome'           => 'Capa',
                'descricao'      => 'Verifica se a capa identifica o trabalho como Defesa de Dissertação.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['DEFESA', 'DISSERTAÇÃO', 'Programa de Pós-Graduação'],
                'ordem_esperada' => 2,
            ],
            // --- Pré-textuais ---
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Resumo',
                'descricao'      => 'Verifica a presença do resumo em português.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Resumo'],
                'ordem_esperada' => 3,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Palavras-chave',
                'descricao'      => 'Verifica a presença de palavras-chave em português.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Palavras-chave', 'Palavras chave'],
                'ordem_esperada' => 4,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Abstract',
                'descricao'      => 'Verifica a presença do resumo em língua inglesa.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Abstract'],
                'ordem_esperada' => 5,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Keywords',
                'descricao'      => 'Verifica a presença de palavras-chave em inglês.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Keywords', 'Key words'],
                'ordem_esperada' => 6,
            ],
            [
                'grupo'          => 'pre_textual',
                'nome'           => 'Sumário',
                'descricao'      => 'Verifica a presença do sumário.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Sumário', 'SUMÁRIO'],
                'ordem_esperada' => 7,
            ],
            // --- Textuais: Introdução e suas subseções obrigatórias ---
            [
                'grupo'          => 'textual',
                'nome'           => 'Introdução',
                'descricao'      => 'Verifica a presença do capítulo de introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Introdução', 'INTRODUÇÃO'],
                'ordem_esperada' => 8,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'O Problema',
                'descricao'      => 'Verifica a subseção referente ao problema de pesquisa dentro da Introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['O Problema', 'Problema de pesquisa', 'Problema'],
                'ordem_esperada' => 9,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Justificativa',
                'descricao'      => 'Verifica a subseção de justificativa dentro da Introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Justificativa'],
                'ordem_esperada' => 10,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Objetivos',
                'descricao'      => 'Verifica a subseção de objetivos (geral e específicos) dentro da Introdução.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Objetivos', 'Objetivo Geral', 'Objetivos Específicos', 'Geral'],
                'ordem_esperada' => 11,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Aderência ao Programa',
                'descricao'      => 'Verifica a subseção obrigatória de aderência ao PPGMSB, que inclui área de concentração, linha de pesquisa e contribuição do trabalho.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Aderência ao Programa', 'Aderência Acadêmica'],
                'ordem_esperada' => 12,
            ],
            // Contribuições: não é seção formal no template — verificação complementar, não impeditiva
            [
                'grupo'           => 'textual',
                'nome'            => 'Contribuições',
                'descricao'       => 'Verifica menção às contribuições do trabalho. No template, este conteúdo está dentro da seção Aderência ao Programa.',
                'impeditivo'      => false,
                'status_se_ausente' => 'atencao',
                'tipo_regra'      => 'texto_flexivel',
                'termos_busca'    => ['Contribuições', 'Contribuição', 'Contribuições do trabalho', 'principal contribuição'],
                'ordem_esperada'  => 13,
            ],
            // --- Textuais: capítulos obrigatórios de defesa ---
            [
                'grupo'          => 'textual',
                'nome'           => 'Estado da Arte ou Revisão de Literatura',
                'descricao'      => 'Verifica a presença de capítulo de revisão, fundamentação teórica ou estado da arte.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Estado da Arte', 'Revisão de Literatura', 'Fundamentação Teórica', 'Referencial Teórico', 'Revisão Bibliográfica'],
                'ordem_esperada' => 14,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Materiais e Métodos',
                'descricao'      => 'Verifica a presença do capítulo metodológico.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Materiais e Métodos', 'Materiais e métodos', 'Metodologia', 'Métodos'],
                'ordem_esperada' => 15,
            ],
            // Resultados e Discussão: aceita capítulo unificado ou separado
            [
                'grupo'          => 'textual',
                'nome'           => 'Resultados e Discussão',
                'descricao'      => 'Verifica a presença do capítulo de resultados (e discussão), podendo estar unificado ou em capítulos separados.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Resultados e Discussão', 'Resultados e discussão', 'Resultados'],
                'ordem_esperada' => 16,
            ],
            [
                'grupo'           => 'textual',
                'nome'            => 'Discussão',
                'descricao'       => 'Verifica a presença de capítulo ou seção de discussão, quando separado dos resultados.',
                'impeditivo'      => false,
                'status_se_ausente' => 'atencao',
                'tipo_regra'      => 'texto_flexivel',
                'termos_busca'    => ['Discussão', 'Discussões'],
                'ordem_esperada'  => 17,
            ],
            [
                'grupo'          => 'textual',
                'nome'           => 'Conclusão ou Considerações Finais',
                'descricao'      => 'Verifica a presença da conclusão ou considerações finais.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Conclusão', 'Conclusões', 'Considerações Finais'],
                'ordem_esperada' => 18,
            ],
            // --- Pós-textuais ---
            [
                'grupo'          => 'pos_textual',
                'nome'           => 'Referências',
                'descricao'      => 'Verifica a presença da seção de referências.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Referências', 'Referências Bibliográficas', 'Bibliografia'],
                'ordem_esperada' => 19,
            ],
            // --- Metadados ---
            [
                'grupo'          => 'metadados',
                'nome'           => 'Metadados do Documento',
                'descricao'      => 'Verifica a presença da seção de metadados, obrigatória ao final do documento conforme template classPPGMSB.',
                'impeditivo'     => true,
                'tipo_regra'     => 'texto_flexivel',
                'termos_busca'   => ['Metadados do Documento', 'INFORMAÇÕES TÉCNICAS', 'Compilado em', 'classPPGMSB', 'PPGMSB-UNEB|classPPGMSB|CERTIFIED'],
                'ordem_esperada' => 20,
            ],
            // --- Técnico complementar ---
            [
                'grupo'           => 'tecnico',
                'nome'            => 'Classe LaTeX oficial',
                'descricao'       => 'Verifica se o documento indica uso da classe oficial do PPGMSB.',
                'impeditivo'      => false,
                'status_se_ausente' => 'atencao',
                'tipo_regra'      => 'classe_latex',
                'termos_busca'    => ['classPPGMSB', 'PPGMSB-UNEB|classPPGMSB|CERTIFIED'],
                'ordem_esperada'  => 21,
            ],
            [
                'grupo'           => 'imagens',
                'nome'            => 'Imagens com resolução mínima recomendada',
                'descricao'       => 'Verifica imagens rasterizadas abaixo de 300 DPI.',
                'impeditivo'      => false,
                'status_se_ausente' => 'nao_aplicavel',
                'tipo_regra'      => 'imagem_dpi',
                'termos_busca'    => null,
                'ordem_esperada'  => 22,
            ],
        ];

        $this->sincronizarItens($checklist, $itens);
    }

    private function sincronizarItens(DocumentoChecklist $checklist, array $itens): void
    {
        $nomesAtivos = array_column($itens, 'nome');

        // Remove itens que não estão mais no checklist atual
        DocumentoChecklistItem::where('documento_checklist_id', $checklist->id)
            ->whereNotIn('nome', $nomesAtivos)
            ->delete();

        foreach ($itens as $item) {
            DocumentoChecklistItem::updateOrCreate(
                [
                    'documento_checklist_id' => $checklist->id,
                    'nome'                   => $item['nome'],
                ],
                [
                    'grupo'           => $item['grupo'],
                    'descricao'       => $item['descricao'] ?? null,
                    'obrigatorio'     => $item['obrigatorio'] ?? true,
                    'impeditivo'      => $item['impeditivo'] ?? false,
                    'status_se_ausente' => $item['status_se_ausente'] ?? 'pendente',
                    'tipo_regra'      => $item['tipo_regra'] ?? 'texto_flexivel',
                    'termos_busca'    => $item['termos_busca'] ?? null,
                    'ordem_esperada'  => $item['ordem_esperada'] ?? null,
                    'ativo'           => true,
                ]
            );
        }
    }
}
