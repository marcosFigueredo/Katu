<?php

namespace App\Console\Commands;

use App\Models\DiscenteProgramaVinculo;
use App\Models\TrabalhoAcademico;
use App\Models\TrabalhoDocumento;
use App\Services\ArquivoTrabalhoService;
use App\Services\DocumentoAnaliseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestarAnaliseDocumento extends Command
{
    protected $signature = 'documento:testar-analise {vinculoId=1} {--detalhes}';

    protected $description = 'Testa a análise formal de um PDF de qualificação.';

    public function handle(
        ArquivoTrabalhoService $arquivoService,
        DocumentoAnaliseService $analiseService
    ): int {
        $vinculoId = (int) $this->argument('vinculoId');

        $vinculo = DiscenteProgramaVinculo::with([
            'discenteProfile.user',
            'programa',
            'linhaPesquisa',
        ])->find($vinculoId);

        if (! $vinculo) {
            $this->error("Vínculo discente não encontrado: {$vinculoId}");
            return self::FAILURE;
        }

        $usuario = $vinculo->discenteProfile?->user;

        if (! $usuario) {
            $this->error('Usuário do discente não encontrado.');
            return self::FAILURE;
        }

        $origemTeste = 'trabalhos/teste.pdf';

        if (! Storage::disk('local')->exists($origemTeste)) {
            $this->error("PDF de teste não encontrado em storage/app/private/{$origemTeste}");
            return self::FAILURE;
        }

        $trabalho = TrabalhoAcademico::updateOrCreate([
            'discente_programa_vinculo_id' => $vinculo->id,
        ], [
            'programa_id' => $vinculo->programa_id,
            'linha_pesquisa_id' => $vinculo->linha_pesquisa_id,
            'titulo' => 'Trabalho de teste para análise documental',
            'nivel' => $vinculo->nivel ?? 'mestrado',
            'status' => 'em_andamento',
            'data_inicio' => $vinculo->data_ingresso,
        ]);

        $tipoDocumento = 'qualificacao';

        $proximaVersao = TrabalhoDocumento::where('trabalho_academico_id', $trabalho->id)
            ->where('tipo_documento', $tipoDocumento)
            ->max('versao');

        $versao = ((int) $proximaVersao) + 1;

        $nomeSeguro = $arquivoService->gerarNomePdf(
            $usuario,
            $tipoDocumento,
            $versao
        );

        $destino = $arquivoService->gerarPathPdf($trabalho->id, $nomeSeguro);

        Storage::disk('local')->copy($origemTeste, $destino);

        $caminhoFisico = Storage::disk('local')->path($destino);

        $documento = TrabalhoDocumento::create([
            'trabalho_academico_id' => $trabalho->id,
            'enviado_por_id' => $usuario->id,
            'tipo_documento' => $tipoDocumento,
            'versao' => $versao,
            'arquivo_pdf_path' => $destino,
            'arquivo_pdf_nome_original' => 'teste.pdf',
            'arquivo_pdf_mime' => 'application/pdf',
            'arquivo_pdf_tamanho' => filesize($caminhoFisico),
            'arquivo_pdf_hash_sha256' => hash_file('sha256', $caminhoFisico),
            'status_analise' => 'aguardando',
            'enviado_em' => now(),
            'observacao' => 'Documento criado automaticamente para teste técnico.',
        ]);

        $analise = $analiseService->analisar($documento);

        $this->info('Análise concluída.');
        $this->line('');

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Discente', $usuario->name],
                ['Trabalho ID', $trabalho->id],
                ['Documento ID', $documento->id],
                ['Arquivo salvo', $destino],
                ['Status geral', $analise->status_geral],
                ['Conformidade', $analise->percentual_conformidade . '%'],
                ['Itens conformes', $analise->itens_conformes],
                ['Itens pendentes', $analise->itens_pendentes],
                ['Itens em atenção', $analise->itens_atencao],
                ['PDF pesquisável', $analise->pdf_pesquisavel ? 'Sim' : 'Não'],
                ['Classe LaTeX detectada', $analise->classe_latex_detectada ? 'Sim' : 'Não'],
            ]
        );

        if ($this->option('detalhes')) {
            $itens = $analise->itens()->with('checklistItem')->orderBy('id')->get();

            $grupos = [
                'capa'         => '── CAPA',
                'tecnico'      => '── TÉCNICO',
                'pre_textual'  => '── PRÉ-TEXTUAIS',
                'textual'      => '── TEXTUAIS',
                'pos_textual'  => '── PÓS-TEXTUAIS',
                'metadados'    => '── METADADOS',
                'imagens'      => '── IMAGENS',
            ];

            $icones = [
                'conforme'     => '✔',
                'pendente'     => '✘',
                'atencao'      => '⚠',
                'nao_aplicavel' => '–',
            ];

            $grupoAtual = null;

            foreach ($itens as $item) {
                $grupo = $item->grupo;

                if ($grupo !== $grupoAtual) {
                    $grupoAtual = $grupo;
                    $this->line('');
                    $this->comment($grupos[$grupo] ?? "── $grupo");
                }

                $icone  = $icones[$item->status] ?? '?';
                $nome   = $item->nome_item;
                $pag    = $item->pagina_detectada ? " [pág. {$item->pagina_detectada}]" : '';
                $imp    = $item->impeditivo ? ' (impeditivo)' : '';

                if ($item->status === 'conforme') {
                    $this->line("  <fg=green>{$icone}</> {$nome}{$pag}");
                } elseif ($item->status === 'atencao') {
                    $this->line("  <fg=yellow>{$icone}</> {$nome}{$pag}{$imp}");
                } else {
                    $this->line("  <fg=red>{$icone}</> {$nome}{$imp}");
                }

                // Evidência
                if ($item->evidencia) {
                    $ev = str_replace(["\n", "\r", "\t"], ' ', $item->evidencia);
                    $ev = preg_replace('/\s+/', ' ', $ev);
                    $this->line("      Encontrado: " . mb_substr($ev, 0, 120));
                }

                // Termos encontrados
                $termos = $item->termos_encontrados;
                if (!empty($termos)) {
                    $this->line("      Termo(s): " . implode(', ', (array) $termos));
                }

                // Termos buscados (quando pendente)
                if ($item->status !== 'conforme' && $item->checklistItem?->termos_busca) {
                    $buscados = implode(', ', $item->checklistItem->termos_busca);
                    $this->line("      Buscou: {$buscados}");
                }

                // Mensagem de falha
                if ($item->mensagem && $item->status !== 'conforme') {
                    $this->line("      Motivo: {$item->mensagem}");
                }
            }

            $this->line('');
        }

        return self::SUCCESS;
    }
}