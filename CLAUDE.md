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
- **Avaliação do orientando** (`AvaliacaoOrientandoController`, `avaliacoes_orientando`): só na defesa, o orientador dá notas de 0 a 10 a 5 elementos da dimensão Autonomia (`AvaliacaoOrientando::ELEMENTOS`); envio único, fora do resultado da banca, privada à CPA; também condição para finalizar.
- **Plágio** (`IntegridadeBancaService`): no formulário sigiloso o avaliador marca "Identifiquei plágio" e indica onde. Todos os avaliadores marcando (confidencial enviada) → a finalização grava `reprovado` e `bancas.reprovada_por_plagio`; só parte → pendência que bloqueia finalizar, ata e folha. Depois de enviada, a avaliação não muda pelo formulário; o alinhamento é no quadro "Plágio" da tela da banca (`avaliacoes.plagio`). O orientador vê o registro da banca — quem marcou e onde (quadro "Plágio — registro da banca") — para as medidas cabíveis; os avaliadores veem onde, sem autoria; a Secretaria não vê. Plágio = jubilamento: o trabalho sai de todas as métricas.
- **Recomendações da qualificação** (`banca_recomendacoes`, `RecomendacoesQualificacaoController`): cada avaliador escreve no formulário padrão da qualificação (uma por linha); na defesa o estudante responde cada uma (atendida, em parte, não atendida; onde está e justificativa) — exigido no pedido de defesa quando há recomendações; a banca da defesa vê as respostas no painel "Informações do estudante".
- **Certificado de participação** (`CertificadoBancaLatexService`, LaTeX): gerado na finalização para cada avaliador e para o presidente e enviado por e-mail; também baixável na tela da banca finalizada (`avaliacoes.certificado`). Leva o nome do coordenador do programa e a portaria de designação (`programas.portaria_coordenador`, em Configurações → Programa); sem assinatura.
- **Documentos** (`TrabalhoDocumentoController`): upload de PDF, extração com `smalot/pdfparser`, análise via Ollama, envio para avaliadores.
- **Submissão enriquecida (estudante)**: no envio do `.zip` (qualificação e defesa) o estudante marca as 4 declarações de `TrabalhoSubmissaoLatex::DECLARACOES` (originalidade, template, IA, plágio — adaptadas da IEEE). Em Meu trabalho → **Dados da pesquisa** (`TrabalhoDadosPesquisaController`, tabela `trabalho_dados_pesquisa`) declara código, dados, seres humanos (CAAE, parecer, TCLE) e animais (protocolo da comissão de ética animal) — exigido para enviar qualquer pedido à homologação. Na **defesa**, responde o **espelho da avaliação** (`EspelhoDiscenteController`, `espelhos_discente`/`espelho_discente_notas`): notas de 0 a 10 às perguntas do barema padrão de defesa (o confidencial não aparece), envio único, dimensão congelada, privado à CPA — exigido para enviar a defesa à homologação. Ainda na defesa: **Impacto da Dissertação** (`TrabalhoImpactoController`, 7 tipos com descrição), **Produção associada** (`ProducaoAssociadaController`: publicações, extensão e popularização; o estudante confirma a lista e o curso define o mínimo de publicações em `programa_cursos.min_publicacoes_defesa` — padrão 1 no mestrado e 4 no doutorado) e **Informações adicionais** (`InformacoesAdicionaisController`: conflito de interesses, disponibilidade de dados, amostras físicas, aprovação ética e certidão do comitê em PDF, uso de IA, originalidade). Enviada a defesa, esses três blocos ficam só leitura até uma devolução.
- **Atividades formativas** (`AtividadeFormativaController` em Configurações, `AtividadesRealizadasController` em Meu trabalho, `AtividadesFormativasService`): a Secretaria define a lista do programa (vale para todos os cursos: nome, detalhes, quantidade exigida, pedido de qualificação ou defesa, informada pelo estudante ou pela Secretaria); o estudante lança as realizadas e sem as exigidas não envia à homologação (a defesa exige também as da qualificação). Na homologação, a Secretaria confere os lançamentos, registra as que ela informa e, na defesa, marca cada publicação (situação; saiu da dissertação; estudante primeiro autor; orientador coautor; coautoria internacional; conta para o mínimo) — pendências bloqueiam a homologação. A banca vê o painel "Informações do estudante" (dados da pesquisa da cópia do pedido, atividades, impacto, produção e Informações adicionais com os PDFs de ética).
- **Usuários** (`UsuarioController`): cadastro com envio automático de e-mail de primeiro acesso. Discente cadastrado à mão exige orientador; o importado por CSV nasce sem orientador (aba/selo "sem orientador").
- **Projetos de pesquisa** (`ProjetoPesquisaService`, `MeuProjetoPesquisaController`, `TrabalhoProjetoController`; tabelas `projetos_pesquisa` e `trabalho_projeto_vinculos`): o próprio docente **permanente** (interno) cadastra seu projeto na Área do Docente (`meu-projeto.*`); um ativo por vez — cadastrar outro conclui o anterior, e não há como ficar sem. Ao definir ou trocar o orientador, e quando o orientador passa a outro projeto, o estudante é vinculado sozinho ao projeto ativo (nunca a concluído); os vínculos nunca se apagam. Em Meu trabalho → Projeto de pesquisa o estudante indica qual objetivo do projeto a pesquisa avança (projeto mudou → diz se mantém). A Secretaria vê o projeto, só leitura, na edição do docente. A CPA verá todos os projetos e objetivos (Etapa 6).
- **Índice de qualidade e CPA mínima** (Etapa 5, versão de teste; `IndiceQualidadeService`, `CpaQualidadeController`, `CpaPeriodoController`, `MembroCpaController`): a Secretaria designa os membros da CPA (`membros_cpa`: mandato e número do ato; Configurações → Membros da CPA). A CPA **não avalia**: em CPA → Períodos e pesos cria os períodos (`periodos_qualidade`, com as faixas das 5 classes) e define pelo SWARA os pesos das 8 dimensões e das 4 fontes (`pesos_qualidade`; sem SWARA, pesos iguais). **Índice do trabalho** (defesa finalizada): mediana dos avaliadores por pergunta, agrupada pela dimensão congelada, + produção associada (pontos sobre o mínimo do curso); média geométrica ponderada; classes 1 Alta · 2 Boa · 3 Suficiente · 4 Pouca · 5 Baixa com regras de corte (reprovado → no mínimo 4; dimensão < 4 → no mínimo 3; Alta exige todas ≥ 7). **Plágio = jubilamento:** a defesa reprovada por plágio sai de todas as métricas (sem índice, fora do programa, da contagem e da lista da CPA; o orientador não vê o quadro). **Índice do programa**: trabalhos + autoavaliação do orientador + avaliação do orientando + espelho, combinados pelos pesos das fontes. A CPA vê em CPA → Qualidade; o orientador (não o coorientador) vê na banca de defesa finalizada o nível e o perfil ("Como a banca interpretou o trabalho"). Calculado na hora, sem gravar. O `admin` não é a CPA. A CPA vê também o **intervalo de confiança de 95%** (bootstrap: avaliadores no trabalho, defesas no programa; semente fixa) e o aviso "fronteira com …" — o orientador não vê. Não há aderência pelo Ollama nem correção do rigor dos avaliadores (decisão do usuário).
- **Perfil CPA — painel** (Etapa 6; `CpaPainelService`, `CpaQualidadeController`, `QuadrienioController`, `acessos_cpa`): o **quadriênio** é cadastrado pela Secretaria (Configurações → Quadriênios, sem sobreposição); a CPA ajusta faixas e pesos. O painel (CPA → Qualidade) mostra **só dados gerais e calculados** — nunca nome de estudante, docente ou título: panorama (índice, classe, IC, distribuição, perfil por fonte, mínimo/quartis/máximo por dimensão), evolução ano a ano, recortes (linha, área, nível, bolsa), pontos fortes e fracos (perguntas), confiabilidade (concordância entre avaliadores, padrão × sigiloso, estudante − banca, orientador − banca) e projetos × objetivos dos estudantes (sem nomes). Filtros: linhas e nível. Mediana e quartis, nunca média. **Conflito de interesse:** aviso quando o recorte inclui trabalhos em que o membro participou e registro de todo acesso (`acessos_cpa`), que a Secretaria vê em Membros da CPA → Acessos. Exportação em planilha (`cpa.exportar`, CSV) e impressão.
- **Conferência da produção no OpenAlex** (`ProducaoOpenAlexService`, `VerificarProducaoOpenAlexJob`, comando `producao:verificar-openalex`): ao adicionar uma publicação, a fila confere pelo DOI (ou, sem DOI, pelo título e ano) se o OpenAlex a encontra e grava `openalex_situacao`/`openalex_citacoes`. **Só informativo:** "não encontrada" não invalida a produção nem muda a nota. Desligável por `OPENALEX_VERIFICAR` (falso nos testes).
- **Bolsas** (`BolsaController`, tabela `bolsas`): a Secretaria registra no cadastro do discente ou depois, na edição (agência, modalidade, processo, início e fim; sem comprovante); o estudante vê em Meu trabalho. Contexto para a CPA, fora da nota.
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
| Presidente finaliza a banca | Avaliadores e presidente (certificado de participação em PDF) | `CertificadoBancaMail` |

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
- Privacidade das respostas: avaliação **confidencial**: o próprio avaliador e o **orientador** (tipo orientador, não o coorientador) veem, avaliador por avaliador — decisão de 2026-11-01; a CPA só agregada e anonimizada; a **autoavaliação** e a **avaliação do orientando** ninguém vê individualmente (a CPA, agregadas); a **Secretaria** não abre relatórios individuais nem consolidados. O orientador vê as avaliações padrão e confidenciais (relatório consolidado) e, nas referências, só as de sua autoria. A **CPA nunca vê um docente nem um trabalho em si** — só dados gerais, exceto projetos de pesquisa e objetivos dos estudantes (sem nomes).
- Ata e folha: na defesa, o orientador só abre/libera depois da autoavaliação; os avaliadores abrem depois do envio (`bancas.ata_enviada_em`); a Secretaria só visualiza.
- A banca de defesa só pode ser criada após a qualificação ser finalizada com aprovação.
- O orientador é presidente da banca via `Orientacao` — não entra em `banca_membros` como avaliador.
- `orientacoes` guarda períodos (`inicio_em`/`fim_em`, `processo_sei`, `justificativa`, `registrado_por`) e nunca apaga linhas; vigente ⇔ `ativo = true`. `TrabalhoAcademico::orientacoes()` e `DocenteProgramaVinculo::orientacoes()` trazem **só as vigentes** (só elas dão acesso); o histórico completo é `orientacoesHistorico()`.
- Cada Secretaria administra um único programa (`ContextoSecretaria`, helpers `programaDaSecretaria()`/`garantirProgramaDaSecretaria()` no `Controller` base); todas as telas da Secretaria filtram por ele.
- Regras de composição de banca e prazos vêm do curso do discente (`programa_cursos`, `curso_regras_banca`, via `VerificacaoBancaService`) — não há mais regra fixa por nível nem sigla UNEB no código.
- ORCID do discente é obrigatório para cadastrar/homologar banca e enviar o trabalho; o discente informa em Meu perfil.
- `PedidoHomologacaoService::enviar` exige, além da composição: ORCID, dados da pesquisa preenchidos, vínculo com o projeto do orientador e objetivo indicado (quando o orientador é permanente ou tem projeto ativo), atividades formativas da etapa e, na defesa, o espelho do discente, o impacto, a produção confirmada com o mínimo de publicações do curso e as Informações adicionais. Cada pedido guarda a cópia dos dados da pesquisa em `bancas.dados_pesquisa_enviados` — é ela que a banca vê.
- Na defesa, o orientador só finaliza (e abre ata e folha) depois da autoavaliação e da avaliação do orientando, e sem pendência de plágio.
- O espelho do discente é privado à CPA: banca, orientador e Secretaria não veem as notas.
- O objetivo do estudante no projeto só muda antes de enviar a qualificação, depois da qualificação finalizada (até enviar a defesa) ou quando o projeto do orientador muda (`ProjetoPesquisaService::objetivoEditavel`).
