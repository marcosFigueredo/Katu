# Autoavaliação do orientador na defesa — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Na banca de defesa, o orientador preenche uma autoavaliação (barema da Secretaria, notas 0–10, privada à CPA) antes de finalizar e enviar ata e folha aos membros; avaliações confidenciais e respostas deixam de aparecer para quem não é CPA; barema ativo passa a ser exigido para homologar; o relatório de referências do orientador mostra só as dele.

**Architecture:** Novo tipo de barema `autoavaliacao_orientador`; um `BaremasDaBancaService` diz quais baremas cada banca exige e quais faltam (usado na homologação, na finalização e nas telas). A autoavaliação vive em tabelas próprias (`autoavaliacoes_orientador` + notas) com controller próprio; `AvaliacaoController::finalizar` ganha a trava e o envio de `AtaFolhaBancaMail`. Privacidade aplicada nos controllers (sem gates/policies, como o resto do projeto).

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8 (porta 3307) e SQLite em memória nos testes, Blade + Bootstrap, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-10-29-autoavaliacao-orientador-design.md`

## Global Constraints

- Nomes em português; rotas `recurso.acao` dentro de `['auth','verified']`; autorização por `users.categoria` e vínculo no controller.
- Rótulo do tipo novo: **"Autoavaliação do orientador"**; valor `autoavaliacao_orientador`.
- Escala de autoavaliação e avaliação: nota de 0 até `nota_maxima` do item (padrão 10). Nota final = `Σ(nota × peso) / Σ(nota_max × peso) × 10`, 2 casas.
- Autoavaliação: só banca `tipo = defesa`, banca homologada e não finalizada, só o orientador vigente (`tipo = orientador`, `ativo = true`); todas as notas obrigatórias; envio único e imutável.
- Privacidade: respostas da autoavaliação ninguém vê (CPA futura); respostas confidenciais só o próprio avaliador; Secretaria não vê respostas de avaliação nenhuma.
- Toda migration com `down()` funcional, testada no SQLite e rodada no MySQL local (`php artisan migrate`, `migrate:rollback --step=1`, `migrate`) ao fim da tarefa que a cria. Enum no SQLite muda com `->change()` (ver `2026_06_24_160000_add_confidencial_tipos_to_baremas.php`).
- **Working tree compartilhado:** há mudanças não commitadas de outra frente (job/compilador LaTeX, leitor `.bbl`, telas `meu-trabalho/*`, `SubmissaoLatexTest`, `CenarioTesteSeeder`, `operacao-fluxo-secretaria.md`). `git add` só dos arquivos da tarefa; a Tarefa 1 trata o seeder (passo 0).
- Commits em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Banca de defesa homologada **antes** desta mudança, sem barema de autoavaliação ativo → o orientador tenta finalizar: recusa com "Barema de Autoavaliação do orientador não cadastrado", nada muda (teste na Tarefa 4).
2. POST forjado da autoavaliação por coorientador, avaliador, outro orientador ou na qualificação → 403/404 e nada gravado; segundo POST depois de enviada → recusado, notas originais intactas (testes na Tarefa 3).
3. Nota fora do intervalo (−1, 11, texto) ou item faltando → erro de validação por item, nada gravado (teste na Tarefa 3).
4. Avaliador abre o relatório individual do **próprio** barema confidencial → continua vendo; o orientador pela URL direta → 403 (teste na Tarefa 5).
5. Orientador cujo trabalho não tem nenhuma referência dele → mensagem "Nenhuma referência de sua autoria foi identificada", sem contadores das demais (teste na Tarefa 6).

---

### Task 1: Tipo de barema "Autoavaliação do orientador" e baremas de teste

**Files:**
- Create: `database/migrations/2026_10_29_100000_add_autoavaliacao_orientador_tipo_to_baremas.php`, `tests/Concerns/CriaBaremasAtivos.php`
- Modify: `app/Models/Barema.php` (constante de rótulos), `app/Http/Controllers/BaremaController.php:62,95` (validação), `resources/views/baremas/create.blade.php`, `resources/views/baremas/edit.blade.php`, `resources/views/baremas/index.blade.php` (opção e rótulo), `database/seeders/CenarioTesteSeeder.php` (`baremasDeTeste`)
- Test: `tests/Feature/BaremaTest.php`, `tests/Feature/CenarioTesteSeederTest.php`

**Interfaces:**
- Produces:
  - `Barema::TIPOS: array<string,string>` = `qualificacao` → "Qualificação", `defesa` → "Defesa", `qualificacao_confidencial` → "Qualificação Confidencial", `defesa_confidencial` → "Defesa Confidencial", `autoavaliacao_orientador` → "Autoavaliação do orientador". Validação do controller usa `Rule::in(array_keys(Barema::TIPOS))`; as três views listam a partir dela.
  - Trait `Tests\Concerns\CriaBaremasAtivos` com `criarBaremasAtivos(Programa $programa, array $tipos = ['qualificacao','qualificacao_confidencial','defesa','defesa_confidencial','autoavaliacao_orientador'], string $nivel = 'ambos'): void` — cada barema ativo com 1 seção e 2 itens (nota_maxima 10, peso 1).

- [ ] **Step 0: Isolar o trabalho alheio no seeder.** `git diff database/seeders/CenarioTesteSeeder.php` mostra a sincronização com a dissertação fictícia feita por outra frente. Commitar só esse arquivo, antes de tocar nele: `chore: cenário de teste sincronizado com a dissertação fictícia`.
- [ ] **Step 1: Testes que falham.** Em `BaremaTest`: `test_secretaria_cria_barema_de_autoavaliacao_do_orientador` (POST `baremas.store` com `tipo = autoavaliacao_orientador` → `assertDatabaseHas('baremas', ['tipo' => 'autoavaliacao_orientador'])`; `baremas.index` mostra "Autoavaliação do orientador"). Em `CenarioTesteSeederTest`: o barema `autoavaliacao_orientador` existe, ativo, com 5 seções e 15 itens.
- [ ] **Step 2: Rodar** `php artisan test tests/Feature/BaremaTest.php tests/Feature/CenarioTesteSeederTest.php` → FAIL (tipo inválido no CHECK do SQLite / barema ausente).
- [ ] **Step 3: Implementar.** Migration estende o enum para os 5 valores (MySQL `MODIFY COLUMN`, SQLite `->change()`); `down()` volta aos 4. `baremasDeTeste` ganha "Autoavaliação do orientador (teste)", versão `teste`, nível `ambos`, nota_maxima 10, peso 1, com estas seções e itens literais (texto do usuário):
  1. **Planejamento e acompanhamento** — "Estabeleci, em conjunto com o orientando, objetivos, expectativas e um cronograma de trabalho claros e factíveis." · "Acompanhei regularmente o desenvolvimento da pesquisa, oferecendo orientações e devolutivas adequadas." · "Identifiquei dificuldades durante o processo de orientação e contribuí para a definição de soluções."
  2. **Disponibilidade e comunicação** — "Mantive disponibilidade adequada para reuniões, esclarecimento de dúvidas e acompanhamento do orientando." · "Estabeleci uma comunicação clara, respeitosa e tempestiva durante o processo de orientação." · "Proporcionei um ambiente favorável ao diálogo, à apresentação de ideias e à manifestação de dúvidas e divergências."
  3. **Formação científica e acadêmica** — "Contribuí para o aprofundamento teórico e o aprimoramento metodológico da pesquisa desenvolvida pelo orientando." · "Estimulei a autonomia intelectual, o pensamento crítico e a capacidade de investigação científica do orientando." · "Incentivei a produção científica, a divulgação dos resultados e o desenvolvimento de uma postura ética e profissional."
  4. **Relação orientador-orientando** — "Considerei as necessidades e particularidades do orientando, respeitando seu ritmo de desenvolvimento acadêmico." · "Mantive uma relação de orientação fundamentada no respeito mútuo, na confiança e na colaboração." · "Conduzi cobranças, dificuldades e eventuais divergências de maneira equilibrada e construtiva."
  5. **Resultados e aperfeiçoamento da orientação** — "Minha atuação contribuiu efetivamente para a evolução acadêmica e científica do orientando." · "Contribuí para que o desenvolvimento da pesquisa permanecesse coerente com os objetivos e prazos estabelecidos." · "Avalio criticamente minha prática de orientação e busco aperfeiçoar meus procedimentos de acompanhamento e formação científica."

  Criar o trait.
- [ ] **Step 4: Rodar** os dois arquivos → PASS; suíte inteira → sem regressão; MySQL migrate/rollback/migrate; `php artisan db:seed --class=CenarioTesteSeeder`.
- [ ] **Step 5: Commit** — `feat: tipo de barema Autoavaliação do orientador e barema de teste com 15 afirmações`.

---

### Task 2: Barema ativo obrigatório para homologar

**Files:**
- Create: `app/Services/BaremasDaBancaService.php`
- Modify: `app/Services/HomologacaoBancaService.php` (`podeHomologar`), `resources/views/bancas/index.blade.php` e `resources/views/bancas/homologacao-show.blade.php` (linha "Baremas cadastrados" nas verificações), fixtures de `tests/Feature/HomologacaoUnicaTest.php` (`criarCenarioCompleto`), `tests/Feature/HomologacaoBancaTest.php`, `tests/Feature/BancaPropostaDiscenteTest.php`, `tests/Feature/PedidoHomologacaoTest.php` (usar o trait da Tarefa 1 onde a suíte acusar)
- Test: `tests/Feature/BaremaObrigatorioTest.php`

**Interfaces:**
- Consumes: `Barema::TIPOS`, trait `CriaBaremasAtivos` (Tarefa 1).
- Produces (`App\Services\BaremasDaBancaService`):
  - `tiposExigidos(Banca $banca): list<string>` — qualificação: `['qualificacao','qualificacao_confidencial']`; defesa: `['defesa','defesa_confidencial','autoavaliacao_orientador']`.
  - `baremaAtivo(Banca $banca, string $tipo): ?Barema` — programa do trabalho, `ativo = true`, `nivel` em `[nivel do trabalho, 'ambos']`, maior `id`.
  - `faltantes(Banca $banca): list<string>` — mensagens "Barema de {Barema::TIPOS[tipo]} não cadastrado." para cada tipo exigido sem barema ativo.
  - `podeHomologar()` passa a devolver também `baremas_ok: bool` e soma as mensagens de `faltantes` em `pendencias`; `pode` exige `baremas_ok`.

- [ ] **Step 1: Testes que falham** em `BaremaObrigatorioTest` (Mail::fake): `test_homologacao_recusada_sem_barema_confidencial_da_qualificacao` (erro de sessão `homologacao` contendo "Barema de Qualificação Confidencial não cadastrado."; status segue `aguardando`); `test_defesa_exige_barema_de_autoavaliacao` (com defesa e defesa_confidencial ativos, sem autoavaliação → recusa com "Barema de Autoavaliação do orientador não cadastrado."); `test_barema_de_outro_nivel_ou_inativo_nao_conta`; `test_com_todos_os_baremas_ativos_homologa`; `test_tela_mostra_pendencia_de_barema` (GET `bancas.index` vê a mensagem).
- [ ] **Step 2: Rodar** `php artisan test tests/Feature/BaremaObrigatorioTest.php` → FAIL.
- [ ] **Step 3: Implementar** o serviço e a integração; nas fixtures de homologação existentes, criar os baremas exigidos com o trait (os testes que homologam passam a falhar sem isso — esperado).
- [ ] **Step 4: Rodar** o arquivo → PASS; suíte inteira → sem regressão.
- [ ] **Step 5: Commit** — `feat: homologação exige barema ativo para cada formulário da etapa`.

---

### Task 3: Autoavaliação do orientador (dados, formulário e bloco na banca)

**Files:**
- Create: `database/migrations/2026_10_29_110000_create_autoavaliacoes_orientador_tables.php`, `app/Models/AutoavaliacaoOrientador.php`, `app/Models/AutoavaliacaoOrientadorNota.php`, `app/Http/Controllers/AutoavaliacaoOrientadorController.php`, `resources/views/avaliacoes/autoavaliacao.blade.php`, `resources/views/avaliacoes/partials/autoavaliacao-bloco.blade.php`
- Modify: `routes/web.php`, `app/Http/Controllers/AvaliacaoController.php@banca` (passa `$autoavaliacao` e `$baremaAutoavaliacao`), `resources/views/avaliacoes/banca.blade.php` (incluir o bloco logo antes de "Status dos avaliadores", linha ~221, só `@if ($ehOrientador && $banca->tipo === 'defesa')`)
- Test: `tests/Feature/AutoavaliacaoOrientadorTest.php`

**Interfaces:**
- Consumes: `BaremasDaBancaService::baremaAtivo($banca, 'autoavaliacao_orientador')` (Tarefa 2).
- Produces:
  - Tabela `autoavaliacoes_orientador`: `banca_id` FK cascade, `docente_programa_vinculo_id` FK, `barema_id` FK restrict, `nota_final` decimal(5,2), `observacoes` text null, `enviada_em` timestamp, timestamps; único `autoaval_orient_banca_docente_unique` (`banca_id`,`docente_programa_vinculo_id`). Tabela `autoavaliacao_orientador_notas`: `autoavaliacao_orientador_id` FK cascade, `barema_item_id` FK, `nota` decimal(5,2); único (`autoavaliacao_orientador_id`,`barema_item_id`) com nome ≤ 64 caracteres.
  - Model `AutoavaliacaoOrientador` (relações `banca`, `barema`, `notas`); `Banca::autoavaliacaoOrientador(): HasOne`.
  - Rotas: `GET /avaliacoes/{banca}/autoavaliacao` → `avaliacoes.autoavaliacao.form`; `POST /avaliacoes/{banca}/autoavaliacao` → `avaliacoes.autoavaliacao.store` (campos `notas[{barema_item_id}]`, `observacoes`). Sucesso redireciona a `avaliacoes.banca` com `success` "Autoavaliação concluída.".
  - Bloco: sem autoavaliação → texto curto e botão "Iniciar autoavaliação" (link para o form); enviada → "Autoavaliação concluída em {d/m/Y H:i}" **sem** notas nem respostas.

- [ ] **Step 1: Testes que falham** em `AutoavaliacaoOrientadorTest` (cenário: defesa homologada `em_avaliacao`, orientador vigente, 2 avaliadores, baremas do trait com o de autoavaliação):
  - `test_bloco_aparece_antes_do_status_dos_avaliadores_so_na_defesa` (`assertSeeInOrder(['Autoavaliação', 'Status dos avaliadores'])`; na qualificação `assertDontSee('Iniciar autoavaliação')`).
  - `test_orientador_envia_autoavaliacao_completa` (POST com todas as notas → linha com `nota_final` correta pela fórmula e `enviada_em`; redirect com "Autoavaliação concluída.").
  - `test_item_faltando_ou_fora_do_intervalo_e_recusado` (nota −1, 11, `'abc'` e item ausente → erros `notas.{id}`; nada gravado) — Review Focus 3.
  - `test_nao_reenvia` (segundo POST → erro `autoavaliacao` "A autoavaliação já foi concluída."; notas originais intactas) — Review Focus 2.
  - `test_coorientador_avaliador_outro_docente_e_qualificacao_recebem_403` — Review Focus 2.
  - `test_respostas_nao_aparecem_para_ninguem` (depois de enviada: orientador vê "Autoavaliação concluída em" e não vê a nota final nem o texto das observações; avaliador e Secretaria não veem nada do bloco).
  - `test_barema_de_autoavaliacao_nao_aparece_para_avaliadores` (avaliador em `avaliacoes.banca` não vê o nome do barema de autoavaliação).
- [ ] **Step 2: Rodar** `php artisan test tests/Feature/AutoavaliacaoOrientadorTest.php` → FAIL.
- [ ] **Step 3: Implementar** migration, models, controller (autorização: docente com vínculo = orientador vigente; `banca->tipo === 'defesa'`; `homologacao_status === 'homologada'`; `status` ≠ `finalizada`/`cancelada`; sem barema ativo → 404 com a mensagem da Tarefa 2), validação `notas.{id}` `required|numeric|min:0|max:{nota_maxima}` para cada item ativo, gravação em transação, views.
- [ ] **Step 4: Rodar** o arquivo → PASS; suíte → sem regressão; MySQL migrate/rollback/migrate.
- [ ] **Step 5: Commit** — `feat: autoavaliação do orientador na banca de defesa`.

---

### Task 4: Finalizar exige autoavaliação e envia ata e folha aos membros

**Files:**
- Create: `database/migrations/2026_10_29_120000_add_ata_enviada_em_to_bancas_table.php`, `app/Mail/AtaFolhaBancaMail.php`, `resources/views/emails/ata-folha-banca.blade.php`
- Modify: `app/Models/Banca.php` (fillable/cast `ata_enviada_em`), `app/Http/Controllers/AvaliacaoController.php` (`finalizar`, `autorizarAta`, `banca` → `$autoavaliacaoPendente`), `resources/views/avaliacoes/banca.blade.php` (botão "Finalizar e enviar ata e folha aos membros"; botões de ata/folha desabilitados com aviso quando pendente)
- Test: `tests/Feature/FinalizarBancaAtaFolhaTest.php`

**Interfaces:**
- Consumes: `BaremasDaBancaService::faltantes` (Tarefa 2), `Banca::autoavaliacaoOrientador()` (Tarefa 3), `FolhaAprovacaoLatexService::gerarPdf(Banca): string` (existente).
- Produces: `bancas.ata_enviada_em` (datetime null); `AtaFolhaBancaMail(Banca $banca, User $membro)` com assunto "Ata e folha de aprovação — Banca de {Qualificação|Defesa}", link `route('avaliacoes.ata', $banca)` e a folha em PDF anexa. Aviso fixo: "Conclua sua autoavaliação para finalizar a banca e liberar a ata e a folha de aprovação."

- [ ] **Step 1: Testes que falham** (Mail::fake; avaliações padrão e confidenciais enviadas por 2 avaliadores):
  - `test_defesa_sem_autoavaliacao_nao_finaliza` (erro `finalizar` com o aviso; status segue `em_avaliacao`; nenhum e-mail).
  - `test_defesa_com_autoavaliacao_finaliza_e_envia_ata_e_folha` (status `finalizada`, `ata_enviada_em` preenchido, `AtaFolhaBancaMail` enviado 2 vezes, uma a cada avaliador, com 1 anexo).
  - `test_qualificacao_finaliza_e_envia_sem_autoavaliacao`.
  - `test_defesa_homologada_sem_barema_de_autoavaliacao_nao_finaliza` — Review Focus 1.
  - `test_orientador_nao_abre_ata_nem_folha_com_autoavaliacao_pendente_na_defesa` (403 nas duas rotas; tela mostra o aviso e não mostra os links ativos).
  - `test_avaliador_abre_ata_e_folha_so_depois_do_envio` (antes: 403; depois de `ata_enviada_em`: 200).
  - `test_secretaria_abre_ata_e_folha_mas_nao_ve_botao_de_envio`.
- [ ] **Step 2: Rodar** `php artisan test tests/Feature/FinalizarBancaAtaFolhaTest.php` → FAIL.
- [ ] **Step 3: Implementar.** Em `finalizar`: depois das checagens atuais, recusar se `faltantes` não vazio ou (defesa e sem autoavaliação enviada); após atualizar a banca, gravar `ata_enviada_em` e enviar o e-mail a cada avaliador com e-mail (depois do commit, como `DB::afterCommit`). `autorizarAta`: orientador em defesa sem autoavaliação → 403; avaliador ativo da banca → permitido se `ata_enviada_em` preenchido.
- [ ] **Step 4: Rodar** o arquivo → PASS; suíte → sem regressão; MySQL migrate/rollback/migrate.
- [ ] **Step 5: Commit** — `feat: finalizar a banca envia ata e folha aos membros e, na defesa, exige a autoavaliação`.

---

### Task 5: Respostas confidenciais e da Secretaria fora do alcance

**Files:**
- Modify: `app/Http/Controllers/AvaliacaoController.php` (`relatorioIndividual`, `relatorioConsolidado`, `banca`), `resources/views/avaliacoes/banca.blade.php` (tabela de status: confidencial mostra só Enviada/Pendente, sem nota nem link), `resources/views/avaliacoes/relatorio-consolidado.blade.php` (sem baremas confidenciais), `tests/Feature/IsolamentoProgramaTest.php` se preciso
- Test: `tests/Feature/PrivacidadeAvaliacoesTest.php`

**Interfaces:**
- Consumes: nada novo.
- Produces: regra única no controller — barema cujo `tipo` termina em `_confidencial` só é exibido ao próprio avaliador; Secretaria recebe 403 em `relatorio-individual` e `relatorio-consolidado`.

- [ ] **Step 1: Testes que falham:**
  - `test_orientador_nao_ve_nota_nem_respostas_confidenciais_na_banca` (vê "Confidencial" e "Enviada"; não vê a nota final confidencial do avaliador).
  - `test_consolidado_do_orientador_nao_tem_barema_confidencial` (não vê o nome do barema confidencial nem suas notas; vê o padrão).
  - `test_orientador_recebe_403_no_relatorio_individual_confidencial` e `test_avaliador_ve_o_proprio_relatorio_confidencial` — Review Focus 4.
  - `test_secretaria_recebe_403_nos_relatorios` (individual padrão, individual confidencial, consolidado).
  - `test_nota_media_da_banca_nao_muda` (finalizar calcula a mesma média de antes, com as confidenciais incluídas).
- [ ] **Step 2: Rodar** `php artisan test tests/Feature/PrivacidadeAvaliacoesTest.php` → FAIL.
- [ ] **Step 3: Implementar** as restrições.
- [ ] **Step 4: Rodar** o arquivo → PASS; suíte → sem regressão.
- [ ] **Step 5: Commit** — `feat: avaliação confidencial e respostas fora do alcance do orientador e da Secretaria`.

---

### Task 6: Referências do orientador, documentação e verificação manual

**Files:**
- Modify: `resources/views/partials/relatorio-referencias.blade.php` (parâmetro `somenteDoOrientador`), `resources/views/avaliacoes/banca.blade.php:118` (passa `somenteDoOrientador => $ehOrientador && ! $ehMembro`), `CLAUDE.md` (módulos, e-mails com `AtaFolhaBancaMail`, pontos de atenção: autoavaliação privada à CPA, confidencial só do avaliador, barema obrigatório), `docs/superpowers/operacao-fluxo-secretaria.md` (cadastrar o barema de autoavaliação; roteiro com `permanente1@ppgmsb.test`)
- Test: `tests/Feature/HomologacaoUnicaTest.php` (junto dos testes de referências existentes)

**Interfaces:**
- Consumes: `submissao_referencias.do_orientador` (existente).
- Produces: com `somenteDoOrientador = true`, a lista e o resumo do partial consideram só `do_orientador = true`; sem nenhuma, a mensagem fixa "Nenhuma referência de sua autoria foi identificada".

- [ ] **Step 1: Testes que falham:** `test_orientador_ve_so_as_referencias_dele` (2 referências, 1 do orientador: vê o título dela, não vê o da outra); `test_orientador_sem_referencias_dele_ve_mensagem` — Review Focus 5; `test_avaliador_continua_vendo_todas`.
- [ ] **Step 2: Rodar** os três → FAIL.
- [ ] **Step 3: Implementar** o filtro e atualizar a documentação.
- [ ] **Step 4: Rodar** → PASS; suíte inteira → sem regressão. Verificação manual (serve + fila): como `permanente1@ppgmsb.test`, na defesa homologada da Juliana — referências só dela; bloco de autoavaliação antes de "Status dos avaliadores"; responder as 15 afirmações; finalizar com as avaliações enviadas; e-mails em `storage/logs/laravel.log`.
- [ ] **Step 5: Commit** — `feat: orientador vê só as próprias referências; documentação da autoavaliação`.
