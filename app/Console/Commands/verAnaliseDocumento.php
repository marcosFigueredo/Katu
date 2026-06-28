<?php

namespace App\Console\Commands;

use App\Models\DocumentoAnalise;
use Illuminate\Console\Command;

class VerAnaliseDocumento extends Command
{
    protected $signature = 'documento:ver-analise {analiseId?}';

    protected $description = 'Mostra o resultado detalhado da análise documental.';

    public function handle(): int
    {
        $analiseId = $this->argument('analiseId');

        $analise = $analiseId
            ? DocumentoAnalise::with('itens')->find($analiseId)
            : DocumentoAnalise::with('itens')->latest('id')->first();

        if (! $analise) {
            $this->error('Nenhuma análise encontrada.');
            return self::FAILURE;
        }

        $this->info('Resumo da análise');
        $this->line('');

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Análise ID', $analise->id],
                ['Documento ID', $analise->trabalho_documento_id],
                ['Status geral', $analise->status_geral],
                ['Conformidade', $analise->percentual_conformidade . '%'],
                ['Itens conformes', $analise->itens_conformes],
                ['Itens pendentes', $analise->itens_pendentes],
                ['Itens em atenção', $analise->itens_atencao],
                ['Itens não aplicáveis', $analise->itens_nao_aplicaveis],
                ['PDF pesquisável', $analise->pdf_pesquisavel ? 'Sim' : 'Não'],
                ['Classe LaTeX detectada', $analise->classe_latex_detectada ? 'Sim' : 'Não'],
                ['Mensagem geral', $analise->mensagem_geral],
            ]
        );

        $this->line('');
        $this->info('Itens avaliados');
        $this->line('');

        $linhas = $analise->itens
            ->sortBy('id')
            ->map(function ($item) {
                return [
                    'Item' => $item->nome_item,
                    'Status' => $item->status,
                    'Página' => $item->pagina_detectada ?? '-',
                    'Evidência' => $this->limitarTexto($item->evidencia),
                    'Mensagem' => $this->limitarTexto($item->mensagem),
                ];
            })
            ->toArray();

        $this->table(
            ['Item', 'Status', 'Página', 'Evidência', 'Mensagem'],
            $linhas
        );

        $this->line('');
        $this->info('Pendências principais');
        $this->line('');

        $pendencias = $analise->itens
            ->where('status', 'pendente')
            ->map(function ($item) {
                return [
                    'Item' => $item->nome_item,
                    'Mensagem' => $item->mensagem,
                    'Evidência' => $item->evidencia ?? '-',
                ];
            })
            ->toArray();

        if (empty($pendencias)) {
            $this->line('Nenhuma pendência encontrada.');
        } else {
            $this->table(
                ['Item', 'Mensagem', 'Evidência'],
                $pendencias
            );
        }

        return self::SUCCESS;
    }

    private function limitarTexto(?string $texto, int $limite = 120): string
    {
        if (! $texto) {
            return '-';
        }

        $texto = preg_replace('/\s+/', ' ', trim($texto));

        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite) . '...';
    }
}