# Etapa 4 — Projetos de pesquisa e bolsa — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cada docente permanente cadastra o próprio projeto de pesquisa e a Secretaria as bolsas dos estudantes; o estudante é vinculado sozinho ao projeto do orientador, indica qual objetivo do projeto sua pesquisa avança e vê suas bolsas.

**Architecture:** Duas tabelas de projeto (`projetos_pesquisa`, `trabalho_projeto_vinculos`, esta com histórico que nunca apaga) e uma de bolsa (`bolsas`). Um `ProjetoPesquisaService` concentra as regras (um ativo por docente, nunca sem projeto, vínculo só com projeto ativo, revinculação quando o projeto do orientador muda) e é chamado pelo `OrientacaoService`. O projeto é cadastrado pelo próprio docente na área dele; a bolsa pela Secretaria na edição do usuário; o estudante usa Meu trabalho.

**Tech Stack:** Laravel 12, Blade + Bootstrap, PHPUnit (SQLite em memória).

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` — seções "Projetos de pesquisa (decidido)" e "Bolsa do estudante (Etapa 4)".

## Global Constraints

- Nomes em português; rotas `recurso.acao`; todas em `['auth', 'verified']`.
- Autorização por `users.categoria` nos controllers; a Secretaria só mexe em usuários do seu programa (`garantirUsuarioDoPrograma`, `programaDaSecretaria()`).
- Banco de desenvolvimento descartável: sem backfill (`migrate:fresh` ok).
- Projeto só para docente **permanente** e **interno** (`docente_programa_vinculos.categoria_docente = 'permanente'`, `tipo_vinculo = 'interno'`).
- Bolsa sem comprovante (decisão do usuário: "o sistema não precisa dessa burocracia").
- Agências: `capes` CAPES · `cnpq` CNPq · `fapesb` FAPESB · `institucional` Institucional · `outra` Outra. Modalidades: `mestrado` Mestrado · `doutorado` Doutorado · `doutorado_sanduiche` Doutorado-sanduíche.
- Commits em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; só arquivos da tarefa.

## Review Focus

1. Docente com projeto ativo recebe outro: todos os orientandos vigentes (orientador, não coorientador) passam ao novo vínculo com objetivo pendente; quem tinha objetivo pode mantê-lo.
2. Troca de orientador para docente colaborador (sem projeto): o vínculo anterior é encerrado e o estudante fica sem projeto — não pode continuar ligado ao projeto do orientador antigo.
3. Docente editando projeto de outro, ou Secretaria de outro programa mexendo em bolsa de usuário alheio → 403/404.
4. Estudante tenta mudar o objetivo fora das janelas (qualificação enviada e ainda não finalizada; defesa enviada) → bloqueado, salvo vínculo novo sem objetivo.
5. Formulário do estudante com "mantenho" e texto vazio, ou "não mantenho" sem texto novo → erro de validação, não objetivo vazio gravado.

---

### Task 1: Projetos, vínculos e regras

**Files:**
- Create: `database/migrations/2026_11_01_100000_create_projetos_pesquisa_tables.php`
- Create: `app/Models/ProjetoPesquisa.php`, `app/Models/TrabalhoProjetoVinculo.php`
- Create: `app/Services/ProjetoPesquisaService.php`
- Modify: `app/Services/OrientacaoService.php` (`definirOrientadorInicial`, `trocarOrientador`)
- Modify: `app/Models/DocenteProgramaVinculo.php` (`projetos()`, `projetoAtivo()`), `app/Models/TrabalhoAcademico.php` (`projetoVinculos()`, `projetoVinculoVigente()`)
- Test: `tests/Feature/ProjetoPesquisaServiceTest.php`

**Interfaces:**
- Produces:
  - `projetos_pesquisa`: `docente_programa_vinculo_id` (fk, cascade), `titulo` string 500, `descricao` text, `inicio_em` date, `fim_em` date null, `situacao` enum `ativo|concluido` default ativo, `registrado_por` fk users null, timestamps.
  - `trabalho_projeto_vinculos`: `trabalho_academico_id` (fk, cascade), `projeto_pesquisa_id` (fk, cascade), `motivo` enum `orientador|troca_orientador|novo_projeto`, `inicio_em` date, `fim_em` date null, `ativo` bool default true, `objetivo` text null, `mantem_objetivo` bool null, `objetivo_informado_em` datetime null, timestamps.
  - `ProjetoPesquisa::SITUACOES = ['ativo' => 'Ativo', 'concluido' => 'Concluído']`.
  - `ProjetoPesquisaService::cadastrar(DocenteProgramaVinculo $docente, array $dados, User $autor): ProjetoPesquisa` — `$dados`: titulo, descricao, inicio_em, fim_em?. Exige docente permanente interno (senão `ValidationException` em `titulo`: "Projeto de pesquisa só para docente permanente do programa."). Conclui o ativo anterior (`situacao = concluido`, `fim_em = fim_em ?? today()`), cria o novo ativo e chama `revincularOrientandos($docente, 'novo_projeto')`.
  - `ProjetoPesquisaService::atualizar(ProjetoPesquisa $projeto, array $dados): void` — corrige titulo/descricao/inicio_em/fim_em do projeto; não revincula; concluído não se edita.
  - `ProjetoPesquisaService::vincular(TrabalhoAcademico $trabalho, string $motivo): ?TrabalhoProjetoVinculo` — encerra o vínculo vigente (`ativo=false`, `fim_em=today()`) se for de outro projeto; acha o orientador vigente (`orientacoes()->where('tipo','orientador')`), o projeto ativo dele; cria o novo vínculo ou devolve null (sem projeto). Se o vigente já é desse projeto, devolve-o sem mudar.
  - `ProjetoPesquisaService::objetivoAnterior(TrabalhoAcademico $trabalho): ?string` — objetivo do último vínculo encerrado que tinha objetivo.
  - Não existe método para concluir projeto isolado (regra "nunca sem projeto").
  - Mudança de categoria: só permanente cadastra; o docente que passa a colaborador mantém o projeto e os vínculos existentes; o colaborador que passa a permanente precisa cadastrar (orientandos ficam bloqueados no pedido até lá).
  - `ProjetoPesquisaService::projetoExigido(TrabalhoAcademico $trabalho): bool` — orientador vigente é permanente interno ou já tem projeto ativo.
  - `ProjetoPesquisaService::objetivoEditavel(TrabalhoAcademico $trabalho): bool` — true se o vínculo vigente ainda não tem objetivo; senão false quando `PedidoHomologacaoService::defesaEnviada` ou quando há qualificação não cancelada com `homologacao_status` aguardando/homologada e `status` diferente de finalizada; true nos demais casos (antes de enviar a qualificação; entre a qualificação finalizada e o envio da defesa).

- [ ] **Step 1: Write the failing tests** em `ProjetoPesquisaServiceTest` (usar `CriaCenarioDeBanca`/fábricas existentes para docente, discente e orientação):
  - `test_cadastrar_conclui_o_anterior_e_deixa_um_so_ativo` — dois `cadastrar` → primeiro `concluido` com `fim_em` hoje, segundo `ativo`; `projetoAtivo()` é o segundo.
  - `test_so_docente_permanente_interno_tem_projeto` — colaborador → `ValidationException`.
  - `test_definir_orientador_vincula_ao_projeto_ativo` — orientador com projeto → `definirOrientadorInicial` cria vínculo `motivo=orientador`, `ativo`, `objetivo` null.
  - `test_orientador_sem_projeto_deixa_estudante_sem_vinculo`.
  - `test_novo_projeto_revincula_orientandos_e_guarda_historico` — estudante com objetivo "X" → novo projeto → vínculo antigo `ativo=false`, novo `motivo=novo_projeto` sem objetivo; `objetivoAnterior()` = "X"; coorientando do mesmo docente **não** é vinculado.
  - `test_troca_para_orientador_sem_projeto_encerra_vinculo` — troca de orientador (antes da qualificação) para colaborador → nenhum vínculo vigente; histórico mantido.
  - `test_troca_de_orientador_vincula_ao_projeto_do_novo` — `motivo=troca_orientador`.

- [ ] **Step 2: Run to verify they fail**
Run: `php artisan test --compact --filter=ProjetoPesquisaServiceTest`
Expected: FAIL (classe/tabela inexistente).

- [ ] **Step 3: Implement** migration, models (casts de datas e booleanos; relações `docenteProgramaVinculo`, `registradoPor`, `projeto`, `trabalhoAcademico`), o service e as chamadas no `OrientacaoService` (dentro da mesma transação, depois de `criar`: `app(ProjetoPesquisaService::class)->vincular($trabalho, 'orientador'|'troca_orientador')`). `revincularOrientandos` percorre `Orientacao::where('docente_programa_vinculo_id', $docente->id)->where('tipo','orientador')->where('ativo', true)` e chama `vincular`.

- [ ] **Step 4: Run tests** — mesma linha: PASS. Depois `php artisan test --compact --filter=Orientacao` : PASS.

- [ ] **Step 5: Commit** — `feat: projetos de pesquisa dos docentes permanentes com vínculo automático do estudante ao projeto do orientador`

---

### Task 2: O docente permanente cadastra o próprio projeto

**Files:**
- Create: `app/Http/Controllers/MeuProjetoPesquisaController.php`
- Create: `resources/views/docente/projeto-pesquisa.blade.php`
- Modify: `resources/views/dashboards/docente.blade.php` (quadro "Meu projeto de pesquisa" no topo: título do ativo ou alerta "Cadastre seu projeto de pesquisa: seus orientandos precisam dele para enviar a qualificação e a defesa.")
- Create: `resources/views/usuarios/partials/projeto-pesquisa.blade.php` (só leitura, na edição do docente, para a Secretaria)
- Modify: `UsuarioController::edit`, `resources/views/usuarios/edit.blade.php`, `routes/web.php`
- Test: `tests/Feature/ProjetoPesquisaTelaTest.php`

**Interfaces:**
- Consumes: `ProjetoPesquisaService::cadastrar`, `::atualizar` (Task 1).
- Produces: rotas `meu-projeto.edit` (GET `/meu-projeto`), `meu-projeto.store` (POST), `meu-projeto.update` (PUT `/meu-projeto/{projeto}`), só `docente` com vínculo permanente interno ativo; o projeto precisa ser dele.

Tela do docente: projeto ativo (título, início, fim ou "em andamento", descrição, nº de orientandos vinculados) com formulário de correção; "Cadastrar novo projeto" (título, descrição, início, fim opcional) com aviso "O projeto atual será concluído e seus orientandos passarão ao novo projeto, indicando se mantêm seus objetivos."; lista dos concluídos. Docente não permanente: 403 nas rotas e o quadro não aparece. A Secretaria vê o bloco só leitura na edição do docente. Validação: titulo required max 500, descricao required max 5000, inicio_em required date, fim_em nullable date after_or_equal:inicio_em.

- [ ] **Step 1: Failing tests**: `test_docente_permanente_cadastra_o_proprio_projeto`; `test_novo_projeto_conclui_o_anterior_pela_tela`; `test_docente_corrige_projeto_ativo`; `test_docente_nao_edita_projeto_de_outro` (403); `test_colaborador_nao_cadastra` (403, quadro ausente); `test_secretaria_ve_projeto_na_edicao_do_docente`; `test_discente_e_secretaria_nao_acessam_rotas` (403); `test_fim_antes_do_inicio_e_recusado`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=ProjetoPesquisaTelaTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: docente permanente cadastra e troca o próprio projeto de pesquisa`

---

### Task 3: Estudante indica o objetivo que sua pesquisa avança

**Files:**
- Create: `app/Http/Controllers/TrabalhoProjetoController.php`
- Create: `resources/views/meu-trabalho/projeto-pesquisa.blade.php`
- Modify: `resources/views/meu-trabalho/index.blade.php` (link "Projeto de pesquisa", com selo "pendente" quando há vínculo vigente sem objetivo)
- Modify: `app/Services/PedidoHomologacaoService.php::enviar` (nova checagem logo após "dados da pesquisa")
- Modify: `routes/web.php`
- Test: `tests/Feature/TrabalhoProjetoTest.php`

**Interfaces:**
- Consumes: `TrabalhoAcademico::projetoVinculoVigente()`, `ProjetoPesquisaService::objetivoAnterior()`, `::objetivoEditavel()`, `::projetoExigido()` (Task 1).
- Produces: rotas `meu-trabalho.projeto.edit` (GET) e `meu-trabalho.projeto.update` (PUT), só `discente`, trabalho do próprio estudante.
- Mensagens do pedido (chave `homologacao`), qualificação e defesa: sem vínculo e `projetoExigido` → `'Seu orientador ainda não cadastrou o projeto de pesquisa. O pedido só pode ser enviado depois que ele cadastrar e você indicar seu objetivo.'`; vínculo sem objetivo → `'Indique, em Meu trabalho → Projeto de pesquisa, qual objetivo do projeto do orientador sua pesquisa avança.'`. Orientador colaborador sem projeto: nada exigido.

Tela: projeto do orientador (título, descrição, início, situação, nome do orientador) e campo "Qual objetivo do projeto sua pesquisa avança?" (textarea, max 3000). Se há `objetivoAnterior` e o vínculo vigente ainda não tem objetivo: mostra o texto anterior e o rádio `mantem_objetivo` = 1 "Mantenho estes objetivos" (grava o texto anterior) / 0 "Não mantenho; indico novos objetivos" (exige `objetivo`). Sem vínculo: "Seu orientador ainda não tem projeto de pesquisa ativo cadastrado." Grava `objetivo_informado_em = now()` a cada envio. Fora das janelas (`objetivoEditavel` false) a tela é só leitura com "O objetivo só pode ser alterado antes de enviar a qualificação, depois da qualificação finalizada (até enviar a defesa) ou quando o projeto do orientador muda."

- [ ] **Step 1: Failing tests**: `test_estudante_ve_projeto_do_orientador_e_informa_objetivo`; `test_mantem_objetivo_anterior_apos_novo_projeto` (grava texto anterior e `mantem_objetivo=true`); `test_nao_mantem_exige_novo_texto` (sem texto → erro `objetivo`); `test_bloqueado_com_qualificacao_enviada_e_liberado_apos_finalizada`; `test_bloqueado_apos_enviar_defesa`; `test_vinculo_novo_sem_objetivo_responde_mesmo_com_defesa_enviada`; `test_orientador_permanente_sem_projeto_bloqueia_pedido`; `test_sem_vinculo_mostra_aviso`; `test_pedido_exige_objetivo_quando_ha_vinculo` (enviar → erro com a mensagem; com objetivo → passa adiante); `test_outro_usuario_nao_acessa`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=TrabalhoProjetoTest` → FAIL.
- [ ] **Step 3: Implement.** Ajustar `CriaCenarioDeBanca` (e demais fixtures que enviam pedido com orientador permanente): criar o projeto do orientador e o objetivo do estudante.
- [ ] **Step 4: Run** o filtro → PASS; `php artisan test --compact --filter=PedidoHomologacao` → PASS.
- [ ] **Step 5: Commit** — `feat: estudante indica o objetivo do projeto do orientador que sua pesquisa avança; exigido no pedido à homologação`

---

### Task 4: Bolsas

**Files:**
- Create: `database/migrations/2026_11_01_110000_create_bolsas_table.php`, `app/Models/Bolsa.php`
- Create: `app/Http/Controllers/BolsaController.php`, `resources/views/usuarios/partials/bolsas.blade.php`
- Modify: `UsuarioController::store` e `resources/views/usuarios/create.blade.php` (campos opcionais de bolsa no bloco do discente), `UsuarioController::edit` + `edit.blade.php` (bloco "Bolsas"), `routes/web.php`
- Modify: `resources/views/meu-trabalho/index.blade.php` (quadro "Bolsa", só leitura)
- Modify: `app/Models/DiscenteProgramaVinculo.php` (`bolsas()`)
- Test: `tests/Feature/BolsaTest.php`

**Interfaces:**
- Produces: `bolsas`: `discente_programa_vinculo_id` (fk, cascade), `agencia` enum (Global Constraints), `agencia_outra` string 150 null (exigido se `outra`), `modalidade` enum, `numero_processo` string 100 null, `inicio_em` date, `fim_em` date null, `registrado_por` fk users null, timestamps. `Bolsa::AGENCIAS`, `Bolsa::MODALIDADES`, accessor `agenciaNome`.
- Rotas `usuarios.bolsas.store` (POST `/usuarios/{usuario}/bolsas`), `usuarios.bolsas.update` (PUT `/usuarios/{usuario}/bolsas/{bolsa}`), `usuarios.bolsas.destroy` (DELETE), só `sec` do programa; a bolsa precisa ser do vínculo do discente nesse programa.
- Regra de validação compartilhada em `Bolsa::regras(string $prefixo = ''): array` (usada no store do usuário com prefixo `bolsa_`, só quando `bolsa_agencia` vier preenchida).

- [ ] **Step 1: Failing tests**: `test_secretaria_registra_bolsa_ao_cadastrar_discente`; `test_secretaria_inclui_edita_e_remove_bolsa_na_edicao`; `test_outra_exige_nome_da_agencia`; `test_fim_antes_do_inicio_e_recusado`; `test_discente_ve_suas_bolsas_em_meu_trabalho`; `test_secretaria_de_outro_programa_nao_mexe`; `test_discente_nao_acessa_rotas_da_secretaria`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=BolsaTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** — `feat: Secretaria registra as bolsas do estudante (agência e modalidade); estudante as vê em Meu trabalho`

---

### Task 5: Cenário de teste e documentação

**Files:**
- Modify: `database/seeders/CenarioTesteSeeder.php` — projeto ativo e um concluído para cada docente permanente orientador (Juliana e demais discentes com orientador); vínculo da Juliana com objetivo; uma bolsa CAPES de mestrado para ela.
- Modify: `CLAUDE.md` (módulos: Projetos de pesquisa e Bolsa; pontos de atenção: nunca sem projeto, vínculo só com ativo, objetivo exigido no pedido), `docs/superpowers/operacao-fluxo-secretaria.md` (roteiro), spec (Etapa 4 feita).
- Test: `php artisan migrate:fresh --seed` sem erro; suíte completa.

- [ ] **Step 1:** Seeder e docs.
- [ ] **Step 2: Run** `php artisan migrate:fresh --seed` → sem erro; `php artisan test --compact` → todos passam.
- [ ] **Step 3: Commit** — `docs: etapa 4 — projetos de pesquisa e bolsa`
