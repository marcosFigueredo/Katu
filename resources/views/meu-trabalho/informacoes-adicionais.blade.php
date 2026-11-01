@use('App\Models\TrabalhoInformacoesAdicionais')
<x-app-layout>
    @php
        $bool = fn (string $campo) => ($v = old($campo, $info->{$campo})) === null ? null : (int) $v;
        $conflito = $bool('conflito_interesses');
        $amostras = $bool('amostras_fisicas');
        $etica = $bool('aprovacao_etica');
        $participantes = $bool('participantes');
        $ia = $bool('uso_ia');
        $disponibilidade = old('disponibilidade_dados', $info->disponibilidade_dados);
    @endphp

    <div class="container-fluid" style="max-width: 900px;"
         x-data="{ conflito: '{{ $conflito }}', disponibilidade: '{{ $disponibilidade }}', amostras: '{{ $amostras }}', etica: '{{ $etica }}', participantes: '{{ $participantes }}', ia: '{{ $ia }}' }">
        <a href="{{ route('meu-trabalho.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Voltar a Meu trabalho
        </a>
        <h1 class="h3 fw-bold mt-2 mb-1" style="color:#00303A;">Informações adicionais</h1>
        <p class="text-muted mb-3">
            Declarações da submissão da defesa. Todas são obrigatórias para enviar a defesa à homologação; a banca vê as respostas.
        </p>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($travado)
            <div class="alert alert-info"><i class="bi bi-lock-fill me-2"></i>{{ \App\Http\Controllers\TrabalhoImpactoController::MENSAGEM_TRAVADO }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meu-trabalho.informacoes.update') }}" enctype="multipart/form-data" class="card border-0 shadow-sm card-body mb-5">
            @csrf
            @method('PUT')
            <fieldset @disabled($travado)>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">1. Conflito de interesses *</legend>
                <p class="form-text mt-0">Você tem algum potencial conflito de interesses a declarar? Isso inclui relações financeiras, pessoais ou profissionais que possam influenciar a forma como a pesquisa é apresentada ou interpretada.</p>
                @include('meu-trabalho._sim-nao', ['campo' => 'conflito_interesses', 'valor' => $conflito, 'modelo' => 'conflito'])
                <div class="mt-2" x-show="conflito === '1'" x-cloak>
                    <label class="form-label small" for="conflito_descricao">Descreva o conflito de interesses.</label>
                    <textarea name="conflito_descricao" id="conflito_descricao" class="form-control" rows="2" maxlength="3000">{{ old('conflito_descricao', $info->conflito_descricao) }}</textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">2. Disponibilidade de dados *</legend>
                <p class="form-text mt-0">Você tem dados suplementares que sustentam os resultados para compartilhar?</p>
                @foreach (TrabalhoInformacoesAdicionais::DISPONIBILIDADE as $v => $r)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="disponibilidade_dados" id="disponibilidade-{{ $v }}" value="{{ $v }}" @checked($disponibilidade === $v) required x-model="disponibilidade">
                        <label class="form-check-label" for="disponibilidade-{{ $v }}">{{ $r }}</label>
                    </div>
                @endforeach
                <div class="mt-2" x-show="disponibilidade === 'repositorio_publico'" x-cloak>
                    <label class="form-label small" for="dados_repositorio">Link do repositório</label>
                    <input type="url" name="dados_repositorio" id="dados_repositorio" class="form-control" maxlength="500" value="{{ old('dados_repositorio', $info->dados_repositorio) }}">
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">3. Disponibilidade de amostras *</legend>
                <p class="form-text mt-0">O estudo envolveu o uso de amostras físicas?</p>
                @include('meu-trabalho._sim-nao', ['campo' => 'amostras_fisicas', 'valor' => $amostras, 'modelo' => 'amostras'])
                <div class="mt-2" x-show="amostras === '1'" x-cloak>
                    <label class="form-label small" for="amostras_declaracao">Informe se as amostras estão disponíveis para outros pesquisadores e como podem ser acessadas. Se não puderem ser compartilhadas, explique brevemente as restrições.</label>
                    <textarea name="amostras_declaracao" id="amostras_declaracao" class="form-control" rows="2" maxlength="3000">{{ old('amostras_declaracao', $info->amostras_declaracao) }}</textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">4. Declaração de ética do PPGMSB *</legend>
                <p class="form-text mt-0">O estudo exige aprovação ética?</p>
                @include('meu-trabalho._sim-nao', ['campo' => 'aprovacao_etica', 'valor' => $etica, 'modelo' => 'etica'])
                <div class="mt-2" x-show="etica === '1'" x-cloak>
                    <label class="form-label small" for="aprovacao_etica_arquivo">Envie o documento original de aprovação. (PDF, até 10 MB)</label>
                    @if ($info->aprovacao_etica_path)
                        <div class="small mb-1"><i class="bi bi-file-earmark-pdf me-1"></i>
                            <a href="{{ route('meu-trabalho.informacoes.etica', ['documento' => 'aprovacao']) }}">{{ $info->aprovacao_etica_nome }}</a>
                            — envie outro arquivo só para substituir.</div>
                    @endif
                    <input type="file" name="aprovacao_etica_arquivo" id="aprovacao_etica_arquivo" class="form-control" accept="application/pdf">
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">5. Consentimento informado *</legend>
                <p class="form-text mt-0">O estudo envolveu participantes humanos ou animais?</p>
                @include('meu-trabalho._sim-nao', ['campo' => 'participantes', 'valor' => $participantes, 'modelo' => 'participantes'])
                <div class="mt-2" x-show="participantes === '1'" x-cloak>
                    <label class="form-label small" for="certidao_etica_arquivo">Envie a certidão do Comitê de Ética em Pesquisa (pessoas) ou da Comissão de Ética no Uso de Animais (animais). (PDF, até 10 MB)</label>
                    @if ($info->certidao_etica_path)
                        <div class="small mb-1"><i class="bi bi-file-earmark-pdf me-1"></i>
                            <a href="{{ route('meu-trabalho.informacoes.etica', ['documento' => 'certidao']) }}">{{ $info->certidao_etica_nome }}</a>
                            — envie outro arquivo só para substituir.</div>
                    @endif
                    <input type="file" name="certidao_etica_arquivo" id="certidao_etica_arquivo" class="form-control" accept="application/pdf">
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">6. Uso de IA na preparação do trabalho *</legend>
                <p class="form-text mt-0">O PPGMSB segue a posição do COPE sobre o uso de Inteligência Artificial (IA) e de tecnologias assistidas por IA na preparação de trabalhos. Os autores devem declarar qualquer uso de IA ou de tecnologias assistidas por IA na preparação do trabalho.</p>
                <div class="alert alert-light small py-2">Importante: ferramentas de IA não podem ser creditadas como autoras. A responsabilidade pela exatidão, originalidade e integridade do trabalho é inteiramente dos autores. Recomenda-se guardar registros com data (versões de rascunho, prompts, comentários de coautores, protocolos de pesquisa), pois o programa pode solicitar documentação de autenticidade.</div>
                <p class="mb-1">Você usou alguma ferramenta de IA generativa?</p>
                @include('meu-trabalho._sim-nao', ['campo' => 'uso_ia', 'valor' => $ia, 'modelo' => 'ia'])
                <div class="mt-2" x-show="ia === '1'" x-cloak>
                    <label class="form-label small" for="ia_ferramentas">Nome e versão da ferramenta (ex.: ChatGPT-4, Claude, Grammarly, DeepL Translator v3)</label>
                    <input type="text" name="ia_ferramentas" id="ia_ferramentas" class="form-control mb-2" maxlength="500" value="{{ old('ia_ferramentas', $info->ia_ferramentas) }}">
                    <label class="form-label small" for="ia_etapas">Em que etapas ou seções do trabalho foram usadas</label>
                    <textarea name="ia_etapas" id="ia_etapas" class="form-control" rows="2" maxlength="3000">{{ old('ia_etapas', $info->ia_etapas) }}</textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold">7. Declaração de originalidade *</legend>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="originalidade_confirmada" id="originalidade_confirmada" value="1" required @checked(old('originalidade_confirmada', $info->originalidade_confirmada))>
                    <label class="form-check-label" for="originalidade_confirmada">Confirmo que este trabalho é original, inédito e não está em avaliação em outro lugar.</label>
                </div>
            </fieldset>

            </fieldset>
            @unless ($travado)
                <div class="text-end"><button class="btn btn-primary">Salvar informações adicionais</button></div>
            @endunless
        </form>
    </div>
</x-app-layout>
