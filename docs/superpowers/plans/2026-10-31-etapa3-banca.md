# Etapa 3 — Banca (plágio, recomendações, avaliação do orientando, certificado) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar o que a banca registra e o que o sistema gera ao encerrá-la: plágio no formulário sigiloso (unanimidade reprova; marcação parcial bloqueia), recomendações da qualificação respondidas pelo estudante na defesa, avaliação do orientando pelo orientador e certificado de participação enviado por e-mail.

**Architecture:** Plágio vira dois campos em `banca_avaliacoes` (só nos baremas confidenciais) e um serviço `IntegridadeBancaService` consultado na finalização, na ata/folha e na tela da banca. Recomendações: tabela própria preenchida pelos avaliadores no formulário padrão da qualificação; respostas do estudante exigidas no pedido de defesa e mostradas no painel "Informações do estudante". Avaliação do orientando: formulário fixo de 5 elementos (dimensão Autonomia), envio único, privado, condição para finalizar a defesa como a autoavaliação. Certificado: PDF em LaTeX (mesmo padrão de `FolhaAprovacaoLatexService`) enviado a cada participante na finalização.

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Bootstrap 5, pdflatex, PHPUnit (SQLite em memória).

**Spec:** `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` — "Dimensão 5 › Recomendações da qualificação / Avaliação do orientando", "Dimensão 8 › Regra do plágio", "Etapas de implementação › 3".

## Global Constraints

- Nomes em português; rotas `recurso.acao`; todas em `['auth', 'verified']`; autorização por `users.categoria` no controller.
- Privacidade: o formulário confidencial só o próprio avaliador vê; autoavaliação e avaliação do orientando ninguém vê no sistema (CPA no futuro); a Secretaria não abre avaliações individuais; o espelho continua privado.
- Confirmações com modal Bootstrap, nunca `confirm()`. Blade com `@use(...)`.
- Dev DB descartável; commits em português com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; só arquivos da tarefa no stage.
- Suíte: `php artisan test --compact`.

## Decisões deste plano

Do usuário (2026-10-31):
- **Certificado sem assinatura:** só o nome do coordenador do curso e a portaria de designação.

Do planejador (para revisão):
1. **Portaria:** novo campo de texto livre `programas.portaria_coordenador` (ex.: "Portaria nº 123/2025, publicada no Diário Oficial do Estado da Bahia em 10/03/2025"), editado com o coordenador em Configurações → Programa. Sem portaria cadastrada, o certificado sai sem essa linha.
2. **Quem recebe o certificado:** cada avaliador da banca e o orientador (presidente), na finalização, por e-mail com o PDF. Também pode ser baixado por eles depois, na tela da banca. Coorientadores não recebem (não compõem a banca).
3. **Plágio nas duas etapas** (qualificação e defesa), pois as duas têm formulário sigiloso. Conta só avaliação **enviada**. Unanimidade = todos os avaliadores ativos com a confidencial enviada e plágio marcado → resultado forçado a "reprovado" e `bancas.reprovada_por_plagio = true` (a CPA lê esse campo quando existir o perfil). Marcação parcial → pendência: não finaliza, não gera ata nem folha.
4. **Quem vê o quê no plágio:** o orientador vê só "Há uma pendência de integridade a ser resolvida pela banca"; os avaliadores veem as indicações de onde, sem o nome de quem marcou; a Secretaria não vê.
5. **Recomendações:** cada avaliador registra no formulário **padrão** da qualificação ("uma por linha"); o estudante vê a lista consolidada sem autoria. Respostas exigidas no pedido de defesa quando a qualificação tem recomendações. "Onde está no texto" é obrigatório em atendida e atendida em parte; justificativa obrigatória em atendida em parte e não atendida.
6. **Avaliação do orientando:** os 5 elementos da dimensão Autonomia que a spec indica (1–4 e 6): Domínio do tema, Domínio do método, Capacidade crítica, Independência nas decisões, Amadurecimento — notas de 0 a 10, observações opcionais. Exigida para finalizar a defesa (como a autoavaliação).

## Review Focus

1. Avaliador muda o check de plágio depois de enviar? (Hoje a avaliação enviada não reabre: o alinhamento exige que a Secretaria reabra — verificar que a pendência parcial mostra esse caminho.)
2. Banca com um avaliador ainda sem a confidencial enviada e outro com plágio marcado → é pendência parcial (bloqueia), não unanimidade.
3. Orientador força "aprovado" com plágio unânime → resultado gravado é "reprovado".
4. Estudante da defesa cuja qualificação não teve recomendações → pedido não exige respostas.
5. Falha na compilação do certificado não desfaz a finalização (como a folha) e é avisada.

---

### Task 1: Plágio no formulário sigiloso

**Files:**
- Create: migration `2026_10_31_140000_add_plagio_to_banca_avaliacoes.php`, `app/Services/IntegridadeBancaService.php`, `tests/Feature/PlagioBancaTest.php`
- Modify: `app/Models/BancaAvaliacao.php`, `app/Models/Banca.php`, `app/Http/Controllers/AvaliacaoController.php` (`salvar`, `finalizar`, `autorizarAta`, `banca`), `resources/views/avaliacoes/form.blade.php`, `resources/views/avaliacoes/banca.blade.php`, `resources/views/avaliacoes/ata.blade.php`

**Interfaces:**
- Produces: `banca_avaliacoes.plagio_identificado` bool default false, `banca_avaliacoes.plagio_indicacao` text null; `bancas.reprovada_por_plagio` bool default false.
- Produces: `IntegridadeBancaService::situacao(Banca $banca): string` — `'nenhuma' | 'parcial' | 'unanime'` (só avaliações confidenciais enviadas dos avaliadores ativos; `unanime` exige pelo menos 1 avaliador e todos marcados); `IntegridadeBancaService::indicacoes(Banca $banca): Collection<string>` (textos, sem autoria).
- Produces: mensagem de pendência `'Há uma pendência de integridade a ser resolvida pela banca. Os avaliadores precisam se alinhar (quem marcou desmarca, ou todos marcam) antes da finalização; para alterar uma avaliação enviada, peça à Secretaria que reabra a banca.'`

- [ ] **Step 1: Testes (falham)** em `PlagioBancaTest`:
  - `test_check_de_plagio_so_no_formulario_confidencial` — form confidencial mostra "Identifiquei plágio"; form padrão não; POST no padrão com `plagio_identificado=1` grava false.
  - `test_plagio_marcado_exige_indicacao` — enviar confidencial com `plagio_identificado=1` e sem `plagio_indicacao` → erro `plagio_indicacao`; desmarcado com texto antigo → grava indicação null.
  - `test_marcacao_parcial_bloqueia_finalizacao_e_documentos` — 1 de 2 avaliadores marca: `situacao` = parcial; `finalizar` → erro com a mensagem de pendência; GET ata e folha pelo orientador → 403; banca do orientador mostra "Há uma pendência de integridade" e não mostra o texto da indicação; tela do outro avaliador mostra o texto da indicação.
  - `test_avaliador_sem_confidencial_enviada_nao_conta_como_unanime` — 1 marcou e enviou, outro com confidencial em rascunho → parcial.
  - `test_unanimidade_reprova_mesmo_com_aprovado_escolhido` — todos marcam; `finalizar` com `resultado=aprovado` → banca `reprovado`, `reprovada_por_plagio = true`, trabalho não muda para qualificado/defendido; ata mostra "Reprovado por plágio identificado pela banca (decisão unânime)".
  - `test_secretaria_nao_ve_indicacoes` — homologação/tela da Secretaria sem o texto da indicação.
- [ ] **Step 2:** `php artisan test --compact --filter=PlagioBancaTest` → FAIL.
- [ ] **Step 3: Implementar** migration, casts/fillable, serviço, campos no formulário confidencial (check + textarea, com Alpine), validação em `salvar` só quando `str_ends_with($barema->tipo, '_confidencial')` (`exclude_unless:plagio_identificado,1|required|string|max:3000`), finalização (`parcial` → erro; `unanime` → `resultado = 'reprovado'`, `reprovada_por_plagio = true`), `autorizarAta` e `folhaAprovacao` recusam com `parcial`, avisos na tela da banca e linha na ata.
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: plágio no formulário sigiloso — unanimidade reprova, marcação parcial bloqueia a finalização`.

### Task 2: Recomendações da qualificação

**Files:**
- Create: migration `2026_10_31_141000_create_recomendacoes_tables.php`, `app/Models/BancaRecomendacao.php`, `app/Models/RecomendacaoResposta.php`, `app/Http/Controllers/RecomendacoesQualificacaoController.php`, `resources/views/meu-trabalho/recomendacoes.blade.php`, `tests/Feature/RecomendacoesQualificacaoTest.php`
- Modify: `app/Http/Controllers/AvaliacaoController.php` (`salvar`), `resources/views/avaliacoes/form.blade.php`, `app/Services/PedidoHomologacaoService.php`, `resources/views/meu-trabalho/index.blade.php`, `resources/views/avaliacoes/partials/informacoes-estudante.blade.php`, `routes/web.php`, `tests/Concerns/CriaItensDaDefesa.php` (nada a criar: sem recomendações, nada é exigido)

**Interfaces:**
- Consumes: `defesaEnviada` e `TrabalhoImpactoController::MENSAGEM_TRAVADO` (Etapa 2b).
- Produces: `banca_recomendacoes` (`banca_id` FK cascade, `banca_membro_id` FK cascade, `texto` string 1000, `ordem` smallint, timestamps); `recomendacao_respostas` (`banca_recomendacao_id` unique FK cascade, `situacao` enum `atendida|parcial|nao_atendida`, `onde` string 500 null, `justificativa` text null, timestamps). `RecomendacaoResposta::SITUACOES` = Atendida · Atendida em parte · Não atendida.
- Produces: `PedidoHomologacaoService::recomendacoesDaQualificacao(TrabalhoAcademico $t): Collection<BancaRecomendacao>` — da qualificação finalizada mais recente.
- Produces: rotas `meu-trabalho.recomendacoes.edit` (GET) / `.update` (PUT) em `/meu-trabalho/recomendacoes`.

- [ ] **Step 1: Testes (falham)** em `RecomendacoesQualificacaoTest`:
  - `test_avaliador_registra_recomendacoes_no_formulario_padrao_da_qualificacao` — campo "Recomendações ao estudante (uma por linha)" no padrão da qualificação, não no confidencial nem na defesa; salvar com 3 linhas (uma em branco) grava 2; salvar de novo substitui as do mesmo membro.
  - `test_estudante_responde_na_defesa` — qualificação finalizada com 2 recomendações; tela lista os textos sem nome do avaliador; PUT com uma atendida (onde "Cap. 3, p. 40") e uma não atendida (justificativa) → 2 respostas.
  - `test_regras_das_respostas` — atendida sem `onde` → erro; parcial sem justificativa → erro; não atendida sem justificativa → erro.
  - `test_pedido_de_defesa_exige_respostas_quando_ha_recomendacoes` — `'Responda as recomendações da qualificação em Meu trabalho antes de enviar a defesa à homologação.'`; sem recomendações → não exige.
  - `test_banca_da_defesa_ve_recomendacoes_e_respostas` — painel "Informações do estudante" da defesa mostra texto, situação e onde.
  - `test_trava_com_pedido_de_defesa_aguardando` e `test_outro_perfil_nao_acessa` (403).
- [ ] **Step 2:** `php artisan test --compact --filter=RecomendacoesQualificacaoTest` → FAIL.
- [ ] **Step 3: Implementar** migration, models, textarea no formulário padrão da qualificação (gravada em `salvar`, no rascunho e no envio), controller do estudante com validação `exclude_unless`, gate em `enviar` depois das Informações adicionais, card no índice (só quando houver recomendações), bloco no painel da banca.
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: recomendações da qualificação registradas pela banca e respondidas pelo estudante na defesa`.

### Task 3: Avaliação do orientando pelo orientador

**Files:**
- Create: migration `2026_10_31_142000_create_avaliacoes_orientando_tables.php`, `app/Models/AvaliacaoOrientando.php`, `app/Models/AvaliacaoOrientandoNota.php`, `app/Http/Controllers/AvaliacaoOrientandoController.php`, `resources/views/avaliacoes/avaliacao-orientando.blade.php`, `tests/Feature/AvaliacaoOrientandoTest.php`
- Modify: `app/Http/Controllers/AvaliacaoController.php` (`finalizar`, `autorizarAta`, `banca`), `resources/views/avaliacoes/banca.blade.php` (bloco junto da autoavaliação), `routes/web.php`, `tests/Concerns/CriaCenarioDeBanca.php` se testes de finalização de defesa existentes precisarem

**Interfaces:**
- Produces: `avaliacoes_orientando` (`banca_id` unique FK cascade, `docente_programa_vinculo_id` FK, `observacoes` text null, `enviada_em`, timestamps); `avaliacao_orientando_notas` (`avaliacao_orientando_id` FK cascade, `elemento` string 30, `nota` decimal 4,2, `dimensao_qualidade_id` FK null; unique avaliação+elemento).
- Produces: `AvaliacaoOrientando::ELEMENTOS = ['dominio_tema' => 'Domínio do tema', 'dominio_metodo' => 'Domínio do método', 'capacidade_critica' => 'Capacidade crítica', 'independencia' => 'Independência nas decisões', 'amadurecimento' => 'Amadurecimento']`, cada nota com a dimensão `autonomia` (`DimensaoQualidade::doCodigo('autonomia')`).
- Produces: rotas `avaliacao-orientando.form` (GET `/bancas/{banca}/avaliacao-orientando`) / `.store` (POST).

- [ ] **Step 1: Testes (falham)** em `AvaliacaoOrientandoTest`:
  - `test_orientador_avalia_o_orientando_na_defesa` — form mostra os 5 elementos; POST com notas 0–10 → 5 notas com dimensão autonomia; segundo envio → erro, notas originais ficam.
  - `test_nota_fora_do_intervalo_ou_faltando_e_recusada`.
  - `test_so_orientador_e_so_na_defesa` — qualificação → 404; avaliador, discente e Secretaria → 403.
  - `test_finalizar_defesa_exige_avaliacao_do_orientando` — com autoavaliação feita e sem esta → erro `'Conclua a avaliação do orientando para finalizar a banca e liberar a ata e a folha de aprovação.'`; ata/folha pelo orientador → 403 até concluir.
  - `test_ninguem_ve_as_notas` — tela da banca (orientador e avaliador) e da Secretaria não mostram as notas, só "Avaliação do orientando concluída".
- [ ] **Step 2:** `php artisan test --compact --filter=AvaliacaoOrientandoTest` → FAIL.
- [ ] **Step 3: Implementar** migration, models, controller (padrão de `AutoavaliacaoOrientadorController`: orientador vigente, banca de defesa homologada, envio único com captura de `UniqueConstraintViolationException`), view com modal de confirmação, condição em `finalizar` e em `autorizarAta` ao lado da autoavaliação, bloco na tela da banca; ajustar fixtures de finalização de defesa existentes.
- [ ] **Step 4:** filtro → PASS; suíte → verde.
- [ ] **Step 5: Commit** `feat: orientador avalia o orientando na defesa (privado à CPA); condição para finalizar`.

### Task 4: Certificado de participação na banca

**Files:**
- Create: migration `2026_10_31_143000_add_portaria_coordenador_to_programas.php`, `app/Services/CertificadoBancaLatexService.php`, `app/Mail/CertificadoBancaMail.php`, `resources/views/emails/certificado-banca.blade.php`, `tests/Feature/CertificadoBancaTest.php`
- Modify: `app/Models/Programa.php`, `app/Http/Controllers/ProgramaController.php`, `resources/views/programas/form.blade.php`, `app/Http/Controllers/AvaliacaoController.php` (`finalizar` + nova ação `certificado`), `resources/views/avaliacoes/banca.blade.php`, `routes/web.php`, `CLAUDE.md` (tabela de e-mails)

**Interfaces:**
- Produces: `programas.portaria_coordenador` string 500 null.
- Produces: `CertificadoBancaLatexService::renderizarTex(Banca $banca, User $participante, string $papel): string` e `gerarPdf(Banca $banca, User $participante, string $papel): string` (caminho em `storage/app/private/bancas/{id}/certificado-{user_id}.pdf`), `$papel ∈ {'presidente','avaliador'}`; mesmo processo pdflatex de `FolhaAprovacaoLatexService`.
- Produces: `CertificadoBancaMail(Banca $banca, User $participante, string $papel)` com o PDF anexo; rota `avaliacoes.certificado` (GET `/avaliacoes/{banca}/certificado`).
- Texto do certificado (exato, com escape LaTeX dos campos): "Certificamos que **{nome}** participou, como {presidente da banca (orientador) | membro avaliador}, da banca de {qualificação | defesa} de {mestrado | doutorado} de **{discente}**, com o trabalho intitulado “{título}”, realizada em {data por extenso}{, em {local}}, no {nome do programa} ({sigla}) da {instituição}." Rodapé: "{nome do coordenador}" / "Coordenador(a) do {sigla}" / "{portaria_coordenador}" (linha omitida se vazia). Sem assinatura.

- [ ] **Step 1: Testes (falham)** em `CertificadoBancaTest`:
  - `test_tex_tem_nomes_papel_coordenador_e_portaria` — `renderizarTex` contém participante, "membro avaliador", discente, título, nome do coordenador, "Coordenador(a) do PPGMSB" e a portaria; com portaria vazia a linha não aparece; caracteres especiais (`&`, `%`, `_`) escapados.
  - `test_finalizar_envia_certificado_a_avaliadores_e_presidente` — `Mail::fake()`, serviço de PDF mockado; após `finalizar`, `CertificadoBancaMail` enviado a cada avaliador (papel avaliador) e ao orientador (papel presidente).
  - `test_falha_no_certificado_nao_desfaz_finalizacao` — serviço lança exceção → banca finalizada e mensagem cita a falha.
  - `test_participante_baixa_o_certificado_depois` — avaliador e orientador de banca finalizada → 200 (PDF); banca não finalizada → 403; outro docente/discente → 403.
  - `test_secretaria_cadastra_portaria_do_coordenador` — PUT do programa grava `portaria_coordenador`.
- [ ] **Step 2:** `php artisan test --compact --filter=CertificadoBancaTest` → FAIL.
- [ ] **Step 3: Implementar** migration, campo no formulário do programa (abaixo do coordenador), serviço LaTeX (layout paisagem simples com logos de `public/images/` quando existirem), mailable, envio em `finalizar` no mesmo laço que a ata (falha contada e avisada), ação de download e botão na tela da banca finalizada; linha na tabela de e-mails do `CLAUDE.md`.
- [ ] **Step 4:** filtro → PASS; suíte → verde; compilar um certificado real no ambiente local com `php artisan tinker` e abrir o PDF.
- [ ] **Step 5: Commit** `feat: certificado de participação na banca, com nome do coordenador e portaria de designação, enviado na finalização`.

### Task 5: Cenário de teste e documentação

**Files:**
- Modify: `database/seeders/CenarioTesteSeeder.php`, `tests/Feature/CenarioTesteSeederTest.php`, `CLAUDE.md`, `docs/superpowers/operacao-fluxo-secretaria.md`, `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md` (marcar o certificado como feito, com a decisão "sem assinatura")

- [ ] **Step 1: Teste (falha)** `test_cenario_tem_portaria_e_recomendacoes_da_qualificacao` — programa com `portaria_coordenador`; qualificação da Juliana com 2 recomendações e respostas em branco (para o roteiro).
- [ ] **Step 2:** `php artisan test --compact --filter=test_cenario_tem_portaria_e_recomendacoes_da_qualificacao` → FAIL.
- [ ] **Step 3:** seeder idempotente; docs: módulos (plágio, recomendações, avaliação do orientando, certificado), pontos de atenção (privacidade da indicação de plágio e da avaliação do orientando), roteiro da Juliana (responder as recomendações antes do envio; orientador faz autoavaliação e avaliação do orientando; certificados no log de e-mail).
- [ ] **Step 4:** filtro → PASS; suíte → verde; `php artisan migrate --force` e seeder no MySQL.
- [ ] **Step 5: Commit** `docs: etapa 3 — plágio, recomendações, avaliação do orientando e certificado`.
