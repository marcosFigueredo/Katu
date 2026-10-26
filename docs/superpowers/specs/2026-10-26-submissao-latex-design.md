# Submissão do trabalho em LaTeX — compilação, extração de elementos vitais e conformidade

Data: 2026-10-26 · Branch base: `feat/programa-cursos-importacao`

## Intenção

Hoje o discente envia um PDF e o app tenta inferir conteúdo e forma a partir do texto extraído, o que é ruidoso. A forma (margens, fonte, espaçamento) é impossível de verificar com confiança no PDF.

A proposta, inspirada na submissão de artigos da IEEE: **o discente envia o projeto LaTeX completo (`.zip`), o sistema compila, extrai os elementos vitais direto do fonte, confere com o cadastro do app e gera o PDF oficial da banca, com linhas numeradas.** Como o documento é LaTeX feito sobre o template do programa, a forma é responsabilidade do template e **não é analisada**.

## Regras de negócio (decididas pelo usuário)

1. **O programa indica o seu template LaTeX.** Pode ter classe própria ou usar uma classe já existente (abnTeX2, memoir, uma classe institucional antiga). O app **não impõe nem sugere** nenhum template; o `classPPGMSB` é só o template do PPGMSB.
2. **Programa sem template cadastrado continua no fluxo atual de PDF, sem nenhuma análise por IA.** As funcionalidades de IA existem apenas para programas com template LaTeX.
3. **O RAG de conformidade com as Normas fica desligado** (dívida técnica). A análise de conteúdo será tratada depois, com outra abordagem; esta etapa não usa RAG.
4. **O app não analisa forma.** Faz duas coisas: (a) extração dos elementos vitais; (b) conformidade do que foi extraído com os dados do app.
5. **O PDF que vai para a banca é o gerado pelo sistema na compilação, com linhas numeradas.** O discente não envia PDF à parte.
6. **Resultado "não ok":** o discente recebe a lista de problemas e o log de compilação resumido e pode reenviar quantas vezes quiser. Sem "ok", o trabalho **não vai à homologação**.
7. **Bloqueiam (impedem o "ok"):** não compilar; falta de título PT, resumo, abstract, palavras-chave PT, keywords EN, nomes dos membros da banca, nome do orientador e nome do coorientador (quando o trabalho tiver coorientador no app).
8. **Referências não bloqueiam e não são avaliadas pela Secretaria** (revisão do usuário, 2026-10-26 tarde). O relatório de verificação vai **apenas aos avaliadores**, junto com o trabalho. A Secretaria só olha referências se uma pergunta da CPA tratar disso. O PDF da banca sai limpo; o relatório vai à parte.
9. **Ordem do fluxo — homologação única** (revisão do usuário, 2026-10-26 tarde). Existe **uma só homologação, feita pela Secretaria**; a banca não homologa, ela avalia.
   1. O **discente** escolhe a banca (membros, data, horário e local) **e** envia o trabalho (`.zip` LaTeX) no mesmo pedido.
   2. A **verificação automática** (compilação + elementos vitais + conformidade com o cadastro) roda primeiro. Sem "ok", o pedido não chega à Secretaria; o discente corrige e reenvia.
   3. A **Secretaria** recebe o pedido com duas verificações de conformidade: **(a) critérios da CPA**, perguntas sim/não que ela responde (ver "Critérios da CPA"); **(b) composição da banca**, conferida automaticamente pelas regras do curso (`VerificacaoBancaService`).
   4. O botão **Homologar** só fica liberado com **"sim" em 100% das perguntas da CPA** e **composição válida**. A Secretaria também pode **devolver** ao discente, e a devolução indica a não conformidade: as perguntas respondidas "não", as regras de composição que falharam e uma observação livre.
   5. **Ao homologar:** os **avaliadores** recebem por e-mail o PDF numerado, o resultado da verificação automática e o relatório das referências. O **orientador e os coorientadores** recebem um e-mail com a data, o horário e o local cadastrados. A carta institucional aos avaliadores **fica para uma etapa posterior**; por ora, o e-mail é o atual (`TrabalhoEnviadoBancaMail`), acrescido desses anexos e links.
10. **Templates variam:** a busca dos elementos deve ser inteligente (não amarrada aos comandos do PPGMSB), e o app indica ao discente apenas se cada elemento **tem ou não tem**.
11. **Dados que o app já conhece são conferidos** com o cadastro, e as divergências são indicadas ao discente, à Secretaria (só informativo; não trava o botão) e aos avaliadores.

12. **Referências:**
    - verificar se existem;
    - verificar cada referência (existência, DOI, ano) no **OpenAlex e no Crossref**, não no Ollama. O Ollama só ajuda a interpretar referências mal formatadas;
    - referência não encontrada aparece como **"não localizada — verificar"**, nunca como inexistente;
    - verificar a **produção do grupo**: se alguma referência tem o orientador como autor;
    - **atualidade:** guardar o percentual de referências com menos de 2 anos de publicação. Se for maior que 70%, o trabalho é marcado como "referências atualizadas";
    - as marcações das referências vão no apêndice da versão de conferência do discente; a banca recebe o relatório à parte (D4).

## Critérios da CPA (revisão do usuário, 2026-10-26 tarde)

Os critérios da CPA são as condições que a comissão estabelece para o discente ir à qualificação ou à defesa. **Não são evidências enviadas pelo discente.**

- **Cadastro (Secretaria, por programa):** a **pergunta** (texto), a **etapa** a que se aplica (Qualificação e/ou Defesa) e o **nível** (Mestrado e/ou Doutorado). A resposta é **sempre sim/não**; não há outros tipos de resposta, pesos nem "obrigatório" (toda pergunta conta). Na homologação entram só as perguntas da etapa da banca e do nível do curso do discente. *Minha decisão:* etapa e nível aceitam marcar um ou os dois, para não obrigar a duplicar a mesma pergunta.
- **Ciclo de vida:** a pergunta **nunca é apagada**. Ela registra a **data de criação** e, quando sai de uso, fica **inativa** e registra a **data de inativação**. Novas perguntas podem surgir a qualquer momento.
- **Na homologação:** a Secretaria responde as perguntas **ativas na data da homologação**. As respostas guardam o texto da pergunta como estava, para que o histórico não mude se ela for inativada ou surgir outra depois.
- **Regra:** só homologa com **100% "sim"**. Um "não" trava o botão Homologar e entra na devolução como não conformidade.
- O modelo atual (`criterios_cpa` com código, tipo de evidência, obrigatório, versão; `banca_evidencias` com arquivo e validação) é substituído por esse. Não há dados de produção a migrar.

## Homologação única — decisões complementares

- **Vale para todos os programas** (decisão do usuário): com template LaTeX ou no fluxo PDF. No fluxo PDF não há verificação automática do LaTeX; o discente propõe a banca e envia o PDF no mesmo pedido, e a Secretaria responde a CPA e vê a composição. O fluxo antigo (banca homologada antes do envio do trabalho) deixa de existir.
- *Minha decisão:* a banca é gravada como **proposta** quando o discente envia o pedido; a verificação automática compara com essa banca proposta. Com a verificação falhando, a proposta segue editável.
- *Minha decisão:* depois de uma devolução, o discente corrige **o mesmo pedido** (membros, data, horário, local e arquivo). As respostas da CPA são zeradas; as de cada tentativa ficam no histórico, com a não conformidade indicada na devolução.
- *Minha decisão:* sem perguntas da CPA ativas para aquela etapa e nível, vale só a composição, e a tela avisa "nenhuma pergunta da CPA ativa". As respostas podem ser salvas aos poucos; o botão Homologar só libera com todas respondidas "sim".
- *Minha decisão:* a tela da Secretaria **não mostra nada das referências**: nem o relatório, nem a atualidade, nem a produção do orientador.
- *Minha decisão:* o `BancaCadastradaMail` deixa de sair na proposta; os e-mails saem só na homologação (avaliadores e, à parte, orientador e coorientadores). A tabela de e-mails do CLAUDE.md é atualizada.
- **Fica para depois:** a carta institucional aos avaliadores.

## Não-objetivos

- Verificação tipográfica ou de layout (margens, fonte, espaçamento). Isso é responsabilidade do template.
- Análise de conteúdo (aderência, ODS, plágio, IA generativa). Fica para uma etapa futura.
- Editor LaTeX online ou integração com Overleaf.
- Exigir que o projeto do discente use exatamente a classe do template do programa (os programas têm liberdade; ver "Decisões minhas", D2).

## Anti-objetivos (seria um fracasso mesmo que funcione)

- Afirmar que uma referência "não existe" quando ela só não está nas bases consultadas.
- Bloquear o discente por divergências de dados que podem ser legítimas: grafia de nome, título com acentuação diferente.
- Executar código arbitrário do `.zip` no servidor: shell-escape, leitura de arquivos fora do diretório de trabalho, zip-slip.
- Amarrar a extração aos comandos do `classPPGMSB` de forma que outro programa não consiga usar.

## Elementos vitais

| Elemento | Extração | Conferido com o app |
| --- | --- | --- |
| Título (PT) | obrigatória | sim: `trabalhos_academicos.titulo` (tolerante a acento/caixa/espaços) |
| Título (EN) | desejável (aviso) | não |
| Resumo + palavras-chave (PT) | obrigatória | não; o texto extraído passa a alimentar `trabalhos_academicos.resumo`/`palavras_chave` |
| Abstract + keywords (EN) | obrigatória | não |
| Autor | desejável (aviso) | sim: nome do discente; ORCID, se presente no `.tex` |
| Orientador e coorientadores | obrigatória (coorientador se existir no app) | sim: `Orientacao` do trabalho; ORCID quando presente |
| Membros da banca | obrigatória (nomes) | sim: banca homologada ativa (nomes, filiação/sigla, ORCID quando presente) |
| Data e local da defesa | se presentes | sim: `bancas.data_hora` / `bancas.local` |
| Referências bibliográficas | desejável (aviso; não bloqueia) | verificação externa (OpenAlex/Crossref), produção do orientador, atualidade |

Cada item mostra ao discente: ✔ encontrado (com o conteúdo extraído) / ✖ não encontrado / ⚠ divergente do cadastro (mostrando "no .tex" × "no sistema").

## Fluxo

1. **Secretaria** cadastra o template LaTeX do programa (`.zip`). A tela atual "Base de Conhecimento (IA)" (`ProgramaDocumentoReferenciaController`) passa a ter **apenas o template do programa**: o card de "Normas e Procedimentos" sai, e o título, o texto explicativo e o item de menu passam a falar de "Template LaTeX do programa". O texto explica que, sem template, o programa usa o envio de PDF sem análise por IA. **Decisão do usuário (2026-10-26): a Secretaria só cadastra o modelo oficial; a tela tem apenas essa ação.** São só: enviar o `.zip`, ver o template atual (nome, data, quem enviou, `.tex` principal), substituir e baixar. O sistema valida que o `.zip` é um projeto LaTeX seguro com `.tex` principal. Não há tela de mapa, nem confirmação, nem escolha de motor. O template também fica disponível para download pelo discente.
2. **Discente** (programa com template, banca liberada para envio) envia o `.zip` do projeto. Não preenche mais título, resumo e palavras-chave à mão: eles vêm da extração.
3. **Job em fila** (`QUEUE_CONNECTION=database`):
   1. descompacta com segurança num diretório temporário isolado;
   2. localiza o `.tex` principal (o que tem `\documentclass` e `\begin{document}`; se houver vários, prefere o de mesmo nome do principal no template, depois `main.tex`, `dissertacao.tex`, `tese.tex`; se continuar ambíguo, dá "não ok" pedindo um só);
   3. compila com `latexmk`, em modo não interativo, com tempo limite e sem shell-escape;
   4. extrai os elementos vitais do fonte, expandindo `\input`/`\include`;
   5. lê as referências citadas no `.bbl` gerado;
   6. confere com o cadastro;
   7. dispara a verificação externa das referências em job separado (é lenta e depende de rede);
   8. compila a **versão da banca com linhas numeradas** (ver D4).
4. **Resultado** na tela de análise: veredito ok / não ok, checklist dos elementos, divergências, relatório das referências, log resumido e downloads das versões em PDF.
5. **Homologação (única, regra 9):** com "ok", o pedido (banca + trabalho) aparece na fila da Secretaria do programa, com o checklist da verificação automática, as divergências, o PDF numerado, as perguntas da CPA para responder e o resultado automático da composição. O relatório das referências **não** aparece para ela (regra 8). Ela **homologa** (liberado só com 100% "sim" e composição válida) ou **devolve indicando a não conformidade**; ao devolver, o discente é avisado por e-mail e reenvia.
6. **Envio na homologação:** aos avaliadores, o PDF numerado (link assinado, como hoje), o resultado da verificação automática e o relatório das referências, no e-mail e na tela de avaliação da banca. Ao orientador e aos coorientadores, e-mail com data, horário e local.

## Decisões minhas (para revisão do usuário)

- **D1 — Como ser "inteligente" na extração.** O mapa de onde cada elemento vive é **descoberto a partir do template do programa**, uma vez só, no upload pela Secretaria. Primeiro por regras sobre variações conhecidas: `\begin{resumo}`, `\begin{abstract}`, `\begin{resumo}[Abstract]` do abnTeX2, `\chapter*{Resumo}`, comandos com nomes como `palavraschave`/`keywords`/`titulo`/`title`/`autor`/`author`/`orientador`/`advisor` e afins. Se as regras não fecharem, o Ollama lê o template e sugere o mapa. O mapa é guardado automaticamente, **sem confirmação da Secretaria** (decisão do usuário: a tela tem só o cadastro). O motor é detectado (comentário `% !TEX program`, `fontspec`/`unicode-math` → xelatex; padrão `pdflatex`). Na submissão do discente, a extração usa o mapa guardado, que é determinístico e rápido, com as mesmas regras genéricas como reserva. Assim a IA fica fora do caminho crítico de cada envio.
- **D2 — Uso da classe do programa.** Não se exige que o projeto use a classe do template. Se a classe for diferente, o sistema mostra um aviso ("projeto não usa a classe do template do programa"), mas o veredito não muda. O usuário afirmou que os programas têm liberdade; estender isso ao discente é uma inferência minha.
- **D3 — Divergências com o cadastro.** O usuário definiu que a *falta* de elementos bloqueia (regra 7). Quanto às *divergências* (um nome no `.tex` diferente do cadastro, data ou local diferentes), elas **não bloqueiam**: aparecem para o discente e para a Secretaria, que decide na homologação documental. Também não bloqueiam: falta de data ou local, uso de outra classe, referências não localizadas, atualidade abaixo de 70% e ausência de produção do orientador.
- **D12 — Quem dispara o envio** (revisto pela regra 9). O envio aos avaliadores é automático na homologação única. O botão "Enviar à banca" do discente deixa de existir no fluxo LaTeX. A homologação de banca que existia antes do envio do trabalho (`HomologacaoBancaController`, banca proposta e homologada sozinha) é absorvida pela homologação única, nos dois fluxos (LaTeX e PDF).
- **D4 — Numeração de linhas e marcações** (confirmada pelo usuário). O sistema gera duas versões a partir do mesmo fonte, sem alterar o projeto do discente, injetando código antes do preâmbulo (`latexmk -usepretex`):
  - **versão da banca:** `lineno` com `\linenumbers`, sem marcações de verificação;
  - **versão de conferência do discente:** a mesma numeração, mais um apêndice gerado pelo sistema, "Relatório de verificação das referências", que lista as referências não localizadas, os DOIs ou anos divergentes, a produção do orientador e o percentual de atualidade.

  Destacar dentro da própria lista de referências fica para depois: depende do estilo bibliográfico (apacite, biblatex, abnTeX2) e não é genérico. Se `lineno` não compilar com a classe do programa, a versão da banca sai sem numeração e com aviso, em vez de travar.
- **D5 — IA nesta etapa.** O Ollama entra apenas para (i) sugerir o mapa do template (D1) e (ii) ajudar a ler referências mal formatadas sem DOI. A análise de aderência do resumo (`OllamaResumoService`) **deixa de rodar** para todos, em linha com a regra 2 e com a análise de conteúdo adiada.
- **D6 — Fluxo PDF existente.** Para programas sem template, nada muda, exceto que as chamadas de IA (Ollama do resumo e RAG) deixam de ser feitas. A checagem determinística por checklist (`DocumentoAnaliseService`) continua.
- **D7 — Desligar o RAG.** Uma flag de configuração (`config('rag.habilitado')`, padrão `false`) impede o disparo do `AnalisarConformidadeRagJob` e esconde o upload de Normas. Código e tabelas permanecem. A dívida técnica fica registrada no README/CLAUDE.md.
- **D8 — Atualidade.** Conta só as referências com ano conhecido. O ano vem da verificação externa quando localizada, senão do `.bbl`. O ano de corte é o do envio: "menos de 2 anos" significa `ano >= ano_envio - 1`. O percentual e o booleano "atualizadas" (> 70%) ficam gravados por submissão e no trabalho, para relatórios. Expor isso como indicador da autoavaliação (A19) fica para depois.
- **D10 — Qualificação e trabalhos em inglês.** Na qualificação, título EN, abstract e keywords não bloqueiam (só aviso, se ausentes). Em trabalho escrito em inglês, os papéis se invertem: título, abstract e keywords EN são os obrigatórios, e o PT vira aviso.
- **D11 — Transição.** O fluxo LaTeX vale para envios feitos depois que o programa cadastra o template. Documentos PDF já enviados continuam válidos. Se o programa remover o template, volta ao fluxo PDF.
- **D9 — Produção do orientador.** Uma referência conta como produção do orientador se (a) o DOI estiver nas obras do orientador já sincronizadas (`orcid_works`) ou (b) a verificação no OpenAlex retornar o orientador como autor, comparando pelo ORCID e, na falta dele, pelo nome normalizado.

## Restrições

- Laravel 12, PHP 8.2, MySQL. Testes em SQLite: atenção a nomes de índices/FKs ≤ 64 caracteres no MySQL (já houve um bug assim).
- Convenções do projeto: nomes em português, rotas `recurso.acao` com `['auth','verified']`, autorização por `users.categoria`, isolamento por programa da Secretaria (`programaDaSecretaria()`).
- Arquivos (zips, PDFs gerados, logs) em `storage/app/private/`, nunca públicos.
- **Ambiente:** o dev tem MiKTeX (`latexmk`, `pdflatex`, `xelatex`, `lualatex`, `biber`, `bibtex`). A produção precisa de TeX Live, o que deve ser documentado. Os testes automatizados **não podem depender** de LaTeX instalado: o compilador fica atrás de uma interface, falsificável nos testes. Um teste opcional de integração roda a compilação real quando houver `latexmk`.
- **Segurança da compilação:**
  - rejeitar entradas do zip com caminho absoluto ou `..`;
  - limitar o tamanho descompactado e o número de arquivos;
  - compilar sem shell-escape (`-no-shell-escape`), com `openin_any`/`openout_any` restritos quando o motor permitir e tempo limite (ex.: 180 s);
  - rodar em diretório temporário descartável;
  - nunca usar o nome de arquivo do discente em comando sem escape.
- Chamadas ao OpenAlex/Crossref com tempo limite, `mailto` no User-Agent (polite pool), cache por DOI/título e tolerância a falhas: se a rede falhar, a referência fica "não verificada — tentar de novo", não "não localizada".
- Templates de teste: `docs/classppgmsb.zip` (classe `classPPGMSB`, memoir, ambientes `resumo`/`newAbstract`, `\palavrasChavePT`/`\palavrasChaveEN`, `\TituloBR`/`\TituloEN`, `\NomeAutor`, `\OrientadorNome`, `\MembroANome`…, `\DataDefesa`, `\LocalDefesa`, bibliografia via `apacite`). Também deve haver ao menos uma fixture com abnTeX2 ou `article` padrão, para provar que a extração não depende do PPGMSB.

## Deixado ao implementador

- Estrutura das tabelas (ex.: `programa_templates_latex`, `trabalho_submissoes_latex`, `submissao_elementos`, `submissao_referencias`), desde que o histórico de submissões e as métricas de referências fiquem consultáveis por programa.
- Formato exato do mapa do template.
- Heurísticas da escolha do `.tex` principal e da leitura do `.bbl` (bibtex e biber).
- Normalização de nomes para comparação: acentos, títulos acadêmicos ("Prof. Dr."), ordem "Sobrenome, Nome".
- Texto e layout do apêndice do relatório de referências.
