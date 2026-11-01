# CLAUDE.md — defesa-ppgmsb

## O que é este projeto

Sistema web Laravel para gerenciar o ciclo completo de qualificações e defesas de dissertações/teses do **PPGMSB** (Programa de Pós-Graduação em Modelagem em Saúde da Bahia — UNEB).

## Stack

- **Backend**: PHP 8.2+ · Laravel 12
- **Banco**: MySQL 8+ (conexão `mysql`, banco `defesa_ppgmsb`)
- **Frontend**: Tailwind CSS 3 · Alpine.js 3 · Bootstrap 5.3.3 · Bootstrap Icons 1.11.3
- **Build**: Vite 7 + Laravel Vite Plugin
- **IA**: Ollama local (modelo `qwen2.5:7b`) para análise de PDF
- **Fila**: `QUEUE_CONNECTION=database` (dev); Redis recomendado em produção
- **Auth**: Laravel Breeze

## Como rodar em desenvolvimento

```bash
composer dev        # serve + queue:listen + vite em paralelo
```

Ou separado:
```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Papéis de usuário (`users.categoria`)

| Valor | Papel |
|-------|-------|
| `sec` | Secretaria — gestão completa |
| `docente` | Avaliador / orientador |
| `discente` | Envio de documentos e acompanhamento |
| `admin` | Reservado para uso futuro — sem lógica implementada |

O campo `categoria` é a única fonte de verdade para autorização — não há gates/policies do Laravel, a lógica de acesso está nos controllers.

## Módulos principais

- **Bancas** (`BancaController`): CRUD + reabrir. Status: `cadastrada → em_avaliacao → finalizada`.
- **Avaliações** (`AvaliacaoController`): formulário de notas por barema, finalização pelo orientador, ata imprimível.
- **Baremas** (`BaremaController` + `BaremaSecaoController` + `BaremaItemController`): instrumentos de avaliação com seções e itens. Nota final = `Σ(nota × peso) / Σ(nota_max × peso) × 10`. Tipos em `Barema::TIPOS`: qualificação, defesa, as duas confidenciais e **Autoavaliação do orientador**. A homologação exige barema ativo de cada tipo da etapa (`BaremasDaBancaService`). Toda pergunta (item) pertence a uma das **8 dimensões da qualidade** (`dimensoes_qualidade`, fixas na migration): Rigor científico, Originalidade, Relevância e impacto, Produção associada, Autonomia do estudante, Aderência ao programa, Processo formativo, Integridade e conformidade. Barema só ativa com todas as perguntas ativas vinculadas; cada nota guarda a dimensão do momento (`dimensao_qualidade_id` nas notas). Planejamento completo em `docs/superpowers/specs/2026-10-30-dimensoes-qualidade.md`.
- **Dados para o Lattes** (`DadosLattesBancaService`, `TrabalhoLattesController`, `TabelaLattesController`): na área do docente, "Bancas do {sigla} de que participou" lista as bancas **finalizadas** (avaliador ou orientador) com o quadro de "Participação em bancas" do Lattes. O estudante informa em Meu trabalho → Dados para o Lattes: áreas do CNPq, setor CNAE, título em inglês, outras informações (PT/EN) e visibilidade. Áreas CNPq e setores CNAE são listas **únicas do sistema** (`areas_cnpq`, `setores_cnae`, carregadas de `database/data/` pelo `TabelasLattesSeeder`) que qualquer Secretaria mantém em Configurações → Áreas CNPq e CNAE. Programa tem `homepage_trabalhos`; instituição tem `pais`.
- **Autoavaliação do orientador** (`AutoavaliacaoOrientadorController`): só na banca de defesa, preenchida uma única vez pelo orientador (presidente); condição para finalizar e liberar ata e folha. Respostas privadas à CPA — ninguém as vê no sistema hoje.
- **Documentos** (`TrabalhoDocumentoController`): upload de PDF, extração com `smalot/pdfparser`, análise via Ollama, envio para avaliadores.
- **Submissão enriquecida (estudante)**: no envio do `.zip` (qualificação e defesa) o estudante marca as 4 declarações de `TrabalhoSubmissaoLatex::DECLARACOES` (originalidade, template, IA, plágio — adaptadas da IEEE). Em Meu trabalho → **Dados da pesquisa** (`TrabalhoDadosPesquisaController`, tabela `trabalho_dados_pesquisa`) declara código, dados, seres humanos (CAAE, parecer, TCLE) e animais (protocolo da comissão de ética animal) — exigido para enviar qualquer pedido à homologação. Na **defesa**, responde o **espelho da avaliação** (`EspelhoDiscenteController`, `espelhos_discente`/`espelho_discente_notas`): notas de 0 a 10 às perguntas do barema padrão de defesa (o confidencial não aparece), envio único, dimensão congelada, privado à CPA — exigido para enviar a defesa à homologação. Ainda na defesa: **Impacto da Dissertação** (`TrabalhoImpactoController`, 7 tipos com descrição), **Produção associada** (`ProducaoAssociadaController`: publicações, extensão e popularização; o estudante confirma a lista e o curso define o mínimo de publicações em `programa_cursos.min_publicacoes_defesa` — padrão 1 no mestrado e 4 no doutorado) e **Informações adicionais** (`InformacoesAdicionaisController`: conflito de interesses, disponibilidade de dados, amostras físicas, aprovação ética e certidão do comitê em PDF, uso de IA, originalidade). Enviada a defesa, esses três blocos ficam só leitura até uma devolução.
- **Atividades formativas** (`AtividadeFormativaController` em Configurações, `AtividadesRealizadasController` em Meu trabalho, `AtividadesFormativasService`): a Secretaria define a lista do programa (vale para todos os cursos: nome, detalhes, quantidade exigida, pedido de qualificação ou defesa, informada pelo estudante ou pela Secretaria); o estudante lança as realizadas e sem as exigidas não envia à homologação (a defesa exige também as da qualificação). Na homologação, a Secretaria confere os lançamentos, registra as que ela informa e, na defesa, marca cada publicação (situação; saiu da dissertação; estudante primeiro autor; orientador coautor; coautoria internacional; conta para o mínimo) — pendências bloqueiam a homologação. A banca vê o painel "Informações do estudante" (dados da pesquisa da cópia do pedido, atividades, impacto, produção e Informações adicionais com os PDFs de ética).
- **Usuários** (`UsuarioController`): cadastro com envio automático de e-mail de primeiro acesso. Discente cadastrado à mão exige orientador; o importado por CSV nasce sem orientador (aba/selo "sem orientador").
- **Orientação** (`OrientacaoDiscenteController` + `OrientacaoService`): bloco na edição do discente em Usuários (não há mais tela "Orientações"). Troca de orientador com processo SEI e justificativa obrigatórios e data automática, só antes da qualificação (bloqueada com qualificação `em_avaliacao`/`finalizada` ou defesa não cancelada); a troca devolve a qualificação ainda `cadastrada`. Coorientadores entram e saem sem SEI, até `programas.max_coorientadores` vigentes.

## E-mails automáticos

| Gatilho | Destinatário | Classe |
|---------|-------------|--------|
| Novo usuário cadastrado | Usuário | `PrimeiroAcessoUsuarioMail` |
| Banca cadastrada | Avaliadores | `BancaCadastradaMail` |
| Discente envia trabalho para banca | Avaliadores | `TrabalhoEnviadoBancaMail` |
| Todos avaliadores enviaram | Orientador | `BancaProntaParaFinalizarMail` |
| Banca finalizada | Discente | `BancaFinalizadaDiscenteMail` |
| Recuperação de senha | Usuário solicitante | `RecuperacaoSenhaTemporariaMail` |
| Troca de orientador devolve qualificação homologada | Avaliadores da banca | `BancaRevisaoTrocaOrientadorMail` |
| Presidente finaliza a banca | Avaliadores da banca (ata + folha em PDF) | `AtaFolhaBancaMail` |

## Convenções do projeto

- Nomes em português (controllers, models, rotas, variáveis).
- Rotas nomeadas no padrão `recurso.acao` (ex.: `bancas.reabrir`, `avaliacoes.form`).
- Todas as rotas protegidas com `['auth', 'verified']` — não deixar rotas fora de middleware.
- PDFs armazenados em `storage/app/private/` — nunca expor publicamente.
- Ata da banca gerada em HTML imprimível (`avaliacoes.ata`), logos em `public/images/`.

## Comandos úteis

```bash
php artisan optimize:clear   # limpar caches (config, rotas, views)
php artisan migrate --force  # migrations em produção
php artisan tinker           # inspeção interativa
```

## Pontos de atenção

- O papel `admin` existe na tabela mas **não tem nenhuma lógica implementada** nos controllers.
- Baremas inativados não afetam avaliações existentes.
- Privacidade das respostas: avaliação **confidencial** só o próprio avaliador vê (CPA no futuro); a **autoavaliação** do orientador ninguém vê; a **Secretaria** não abre relatórios individuais nem consolidados. O orientador vê as avaliações padrão e, nas referências, só as de sua autoria.
- Ata e folha: na defesa, o orientador só abre/libera depois da autoavaliação; os avaliadores abrem depois do envio (`bancas.ata_enviada_em`); a Secretaria só visualiza.
- A banca de defesa só pode ser criada após a qualificação ser finalizada com aprovação.
- O orientador é presidente da banca via `Orientacao` — não entra em `banca_membros` como avaliador.
- `orientacoes` guarda períodos (`inicio_em`/`fim_em`, `processo_sei`, `justificativa`, `registrado_por`) e nunca apaga linhas; vigente ⇔ `ativo = true`. `TrabalhoAcademico::orientacoes()` e `DocenteProgramaVinculo::orientacoes()` trazem **só as vigentes** (só elas dão acesso); o histórico completo é `orientacoesHistorico()`.
- Cada Secretaria administra um único programa (`ContextoSecretaria`, helpers `programaDaSecretaria()`/`garantirProgramaDaSecretaria()` no `Controller` base); todas as telas da Secretaria filtram por ele.
- Regras de composição de banca e prazos vêm do curso do discente (`programa_cursos`, `curso_regras_banca`, via `VerificacaoBancaService`) — não há mais regra fixa por nível nem sigla UNEB no código.
- ORCID do discente é obrigatório para cadastrar/homologar banca e enviar o trabalho; o discente informa em Meu perfil.
- `PedidoHomologacaoService::enviar` exige, além da composição: ORCID, dados da pesquisa preenchidos, atividades formativas da etapa e, na defesa, o espelho do discente, o impacto, a produção confirmada com o mínimo de publicações do curso e as Informações adicionais. Cada pedido guarda a cópia dos dados da pesquisa em `bancas.dados_pesquisa_enviados` — é ela que a banca vê.
- Dívida registrada: certificado de participação na banca (gerado após o encerramento, por e-mail, assinado pelo coordenador do curso com a portaria de nomeação no Diário Oficial em destaque) — ainda não implementado.
- O espelho do discente é privado à CPA: banca, orientador e Secretaria não veem as notas.
