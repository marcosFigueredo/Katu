# Etapa 2a — Declarações e espelho do discente — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O estudante declara, no envio do projeto, originalidade, conformidade, uso de IA e ausência de plágio; informa código, dados e ética da pesquisa; e, na defesa, dá nota de 0 a 10 às mesmas perguntas da banca (espelho). Sem isso o pedido não vai à homologação.

**Architecture:** Declarações de submissão gravadas na própria `trabalho_submissoes_latex` (marcadas e com data). Dados da pesquisa numa tabela 1:1 com o trabalho, com tela própria em Meu trabalho. Espelho em tabelas próprias (`espelhos_discente` + notas com dimensão congelada), formulário a partir do barema padrão de defesa. `PedidoHomologacaoService::enviar` passa a exigir dados da pesquisa e, na defesa, o espelho.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8 / SQLite, Blade + Bootstrap, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` — seções "Regras gerais" (espelho), "Declarações do estudante" A e B, "Dimensão 8" (plágio).

## Global Constraints

- Textos das declarações A (rótulos dos checks), em português, adaptados da IEEE (a redação oficial pode substituir depois):
  1. "Declaro que o trabalho é inédito, não foi apresentado como dissertação ou tese em outro programa e não está publicado na íntegra (versões em servidor de preprint e artigos derivados são permitidos)."
  2. "Declaro que o trabalho foi preparado de acordo com o template e as normas do programa."
  3. "Declaro que todo uso de inteligência artificial (texto, figuras, imagens, código) está informado nos agradecimentos, com o sistema usado, as seções em que foi usado e o nível de uso — ou que não houve uso."
  4. "Declaro que não há plágio neste trabalho."
  O item "verificação prévia" é **informativo** (o Katu já faz), sem check.
- Declarações A valem no envio do `.zip` da **qualificação e da defesa**.
- Dados da pesquisa (B): código (não houve / público+link / sob solicitação / não compartilhável+justificativa); dados (tipos múltiplos; onde: público+link ou DOI / no trabalho / sob solicitação / não compartilhável+justificativa / não se aplica); seres humanos (não / sim → CAAE, nº do parecer, TCLE ou dispensa justificada); animais (não / sim → nº do protocolo).
- Espelho: só **na defesa**; perguntas do **barema padrão de defesa** (o sigiloso não aparece ao estudante); nota 0 a `nota_maxima`; todas obrigatórias; envio único; nota com dimensão congelada. **Privado à CPA** (banca e orientador não veem as notas do espelho).
- Escala 0–10 em tudo, exceto os checks de sim/não.
- Migrations com `down()`, SQLite e MySQL; commits em português com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; `git add` só dos arquivos da tarefa (há trabalho não commitado de outra frente em `SubmissaoLatexController`, `meu-trabalho/*` e `SubmissaoLatexTest` — **essas mudanças alheias não podem entrar nos commits**; se a tarefa precisar alterar um desses arquivos, Ruling no ledger e commit do arquivo inteiro só com autorização do usuário).

## Review Focus

1. Envio do `.zip` com um dos 4 checks desmarcado (ou forjado) → recusado, nada gravado (Tarefa 1).
2. "Sim" para seres humanos sem CAAE ou sem parecer; repositório público sem link; "não compartilhável" sem justificativa → erro no campo (Tarefa 2).
3. Pedido de qualificação sem dados da pesquisa → não vai à homologação; com dados → vai (Tarefa 2).
4. Pedido de defesa sem espelho → bloqueado; espelho enviado duas vezes → recusado, notas originais intactas (Tarefa 3).
5. Avaliador ou orientador tentando ver o espelho (URL direta) → 403 (Tarefa 3).

---

### Task 1: Declarações de submissão no envio do projeto

**Files:** Create migration `2026_10_30_110000_add_declaracoes_to_submissoes_latex.php`; Modify `app/Models/TrabalhoSubmissaoLatex.php` (fillable/cast `declaracoes` array, `declarado_em`; constante `DECLARACOES` com os 4 textos), `app/Http/Controllers/SubmissaoLatexController.php@store` (4 checks `accepted`), `resources/views/meu-trabalho/index.blade.php` (4 checks + nota informativa da verificação prévia no formulário do `.zip`); Test `tests/Feature/DeclaracoesSubmissaoTest.php`.

**Interfaces:** Produces `trabalho_submissoes_latex.declaracoes` (json: chaves `originalidade`, `template`, `ia`, `plagio` → true) e `declarado_em`; `TrabalhoSubmissaoLatex::DECLARACOES` (chave → texto).

- [ ] **Step 1: Testes que falham:** tela do envio mostra os 4 textos; envio sem algum check → erro naquele campo e nenhuma submissão criada (Review Focus 1); envio com os 4 → submissão com `declaracoes` e `declarado_em`.
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar.** **Step 4: Rodar** → PASS; suíte; MySQL. **Step 5: Commit** — `feat: declarações de originalidade, template, IA e plágio no envio do projeto`.

---

### Task 2: Dados da pesquisa (código, dados e ética)

**Files:** Create migration `2026_10_30_111000_create_trabalho_dados_pesquisa_table.php`, `app/Models/TrabalhoDadosPesquisa.php`, `app/Http/Controllers/TrabalhoDadosPesquisaController.php`, `resources/views/meu-trabalho/dados-pesquisa.blade.php`; Modify `app/Models/TrabalhoAcademico.php` (`dadosPesquisa(): HasOne`), `routes/web.php` (`meu-trabalho.dados-pesquisa.edit`/`update`), `resources/views/meu-trabalho/index.blade.php` (card), `app/Services/PedidoHomologacaoService.php@enviar` (exige dados preenchidos); Test `tests/Feature/DadosPesquisaTest.php`.

**Interfaces:** Produces tabela `trabalho_dados_pesquisa` (único por trabalho): `codigo` enum(`nao_houve`,`publico`,`sob_solicitacao`,`nao_compartilhavel`), `codigo_link`, `codigo_justificativa`; `dados_tipos` json (`primarios`,`secundarios_publicos`,`secundarios_restritos`,`simulados`), `dados_disponibilidade` enum(`nao_se_aplica`,`publico`,`no_trabalho`,`sob_solicitacao`,`nao_compartilhavel`), `dados_link`, `dados_justificativa`; `seres_humanos` bool, `caae`, `parecer_numero`, `tcle` enum(`termo`,`dispensa`) null, `tcle_justificativa`; `animais` bool, `ceua_protocolo`; `preenchido_em`. Mensagem do bloqueio: "Preencha os dados da pesquisa (código, dados e ética) em Meu trabalho antes de enviar à homologação."

- [ ] **Step 1: Testes que falham:** salva o caso completo; validações condicionais (Review Focus 2); pedido sem dados → erro `homologacao` com a mensagem; com dados → segue (Review Focus 3); outro perfil → 403.
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar** (ajustar fixtures de testes que enviam à homologação: criar os dados da pesquisa). **Step 4: Rodar** → PASS; suíte; MySQL. **Step 5: Commit** — `feat: estudante declara código, dados e ética da pesquisa; exigido para enviar à homologação`.

---

### Task 3: Espelho do discente na defesa

**Files:** Create migration `2026_10_30_112000_create_espelhos_discente_tables.php`, `app/Models/EspelhoDiscente.php`, `app/Models/EspelhoDiscenteNota.php`, `app/Http/Controllers/EspelhoDiscenteController.php`, `resources/views/meu-trabalho/espelho.blade.php`; Modify `routes/web.php` (`meu-trabalho.espelho.form`/`store`), `resources/views/meu-trabalho/index.blade.php` (card na defesa), `app/Services/PedidoHomologacaoService.php@enviar` (defesa exige espelho); Test `tests/Feature/EspelhoDiscenteTest.php`.

**Interfaces:** Consumes `BaremasDaBancaService::baremaAtivo($banca, 'defesa')`, `BaremaItem::dimensao_qualidade_id`. Produces `espelhos_discente` (`banca_id` único, `barema_id`, `nota_final`, `enviada_em`) e `espelho_discente_notas` (`espelho_discente_id`, `barema_item_id`, `dimensao_qualidade_id`, `nota`). Mensagem do bloqueio: "Responda o espelho da avaliação (suas notas de 0 a 10 às perguntas da banca) antes de enviar a defesa à homologação." Confirmação do envio em modal (como na autoavaliação): "Enviar suas notas? Depois de enviadas, não podem ser alteradas."

- [ ] **Step 1: Testes que falham:** formulário mostra as perguntas do barema padrão de defesa e não as do sigiloso; envio completo grava notas com dimensão; item faltando/fora do intervalo → erro; segundo envio → recusado (Review Focus 4); qualificação não pede espelho; pedido de defesa sem espelho → bloqueado, com espelho → segue; avaliador, orientador e Secretaria não acessam (Review Focus 5).
- [ ] **Step 2: Rodar** → FAIL. **Step 3: Implementar.** **Step 4: Rodar** → PASS; suíte; MySQL. **Step 5: Commit** — `feat: espelho do discente na defesa — notas de 0 a 10 às perguntas da banca`.

---

### Task 4: Documentação e cenário de teste

**Files:** Modify `CLAUDE.md`, `docs/superpowers/operacao-fluxo-secretaria.md`, `database/seeders/CenarioTesteSeeder.php` (dados da pesquisa da Juliana preenchidos); Test `tests/Feature/CenarioTesteSeederTest.php`.

- [ ] **Step 1:** Teste do seeder (dados da pesquisa da Juliana existem) → FAIL → implementar → PASS. **Step 2:** Documentação. **Step 3:** Suíte; seed no MySQL. **Step 4: Commit** — `docs: declarações, dados da pesquisa e espelho do discente`.
