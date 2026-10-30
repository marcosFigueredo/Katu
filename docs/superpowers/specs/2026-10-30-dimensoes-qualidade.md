# Dimensões da qualidade das dissertações — planejamento (em construção)

Início: 2026-10-30 · Status: **em discussão** — o usuário está buscando mais informações. Nada aqui foi implementado.

## Objetivo

Apoiar a CPA na análise do item da ficha da CAPES **"Qualidade das teses, dissertações ou equivalentes e adequação às áreas de concentração e linhas de pesquisa do Programa"**, guardando de forma integrada, pontuável e analisável estatisticamente tudo o que os instrumentos do Katu medem.

## Regras gerais (decididas)

- A análise é feita **apenas por dimensões** (sem subdivisão em pilares).
- **Toda pergunta** dos instrumentos é vinculada a **uma dimensão**.
- **Todos os itens são medidos de 0 a 10** (escala tipo Likert de 0 a 10).
- A qualidade envolve as **8 dimensões** abaixo — nenhuma é descartada.
- A análise segue **dimensão por dimensão** ("vamos por item").
- **Espelho do discente:** toda pergunta que a banca avalia também é **indicada pelo discente na submissão da defesa** (o discente responde às mesmas perguntas). Isso permite comparar, pergunta a pergunta, o que o estudante declara com o que a banca reconhece.

## As 8 dimensões

1. Rigor científico
2. Originalidade
3. Relevância e impacto
4. Produção associada *(era "Comunicação científica" — renomeada e redefinida em 2026-10-30)*
5. Autonomia do estudante
6. Aderência ao programa
7. Processo formativo
8. Integridade e conformidade

## Modelo para cada dimensão (proposto; a confirmar na dimensão 1)

1. **Perguntas** vinculadas à dimensão (formulários da banca aberto e sigiloso, qualificação e defesa; autoavaliação; avaliação da orientação pelo estudante; critérios da comissão) + medidas automáticas convertidas para 0–10.
2. **Junção:** por avaliador (média ponderada pelo peso do item) → por banca (média dos avaliadores; guardar aberto, sigiloso e combinado) → por etapa (defesa = principal; qualificação guardada para evolução).
3. **Pontuação 0–10** da dimensão.
4. **Estatística:** média, desvio-padrão e nº de avaliadores por dissertação (desvio > 2 = "banca dividida"); concordância entre avaliadores (correlação intraclasse); consistência das perguntas (alfa de Cronbach); aberto × sigiloso (teste pareado de Wilcoxon); distribuição por ano, linha e orientador; evolução qualificação → defesa.
5. **Guardar:** cada resposta com a dimensão **congelada no momento da avaliação** (avaliador, formulário, etapa) e o resultado calculado por dissertação e dimensão (notas, desvio, n, data do cálculo).

Em aberto: peso das medidas automáticas na nota da dimensão (proposta inicial: 80% banca / 20% medida automática).

## Dimensão 1 — Rigor científico (em discussão)

### Elementos medidos de 0 a 10 no formulário da banca
- **Honestidade** na apresentação, execução e descrição de métodos e procedimentos e na interpretação dos resultados.
- **Confiabilidade** na execução da pesquisa e na comunicação das conclusões.
- **Objetividade** na coleta e no tratamento de dados e informações, na apresentação de provas e evidências e na interpretação de resultados.
- **Imparcialidade** na execução da pesquisa, na comunicação e no julgamento das contribuições de outros.
- **Cuidado** na coleta, armazenamento e tratamento de dados e informações (apoiado pela declaração de dados do estudante).
- **Respeito** por participantes e objetos da pesquisa (seres humanos, animais, meio ambiente, objetos culturais).
- **Veracidade** na atribuição dos créditos a trabalhos de outros.
- **Planejamento** do trabalho de pesquisa — avaliado pela banca no formulário **sigiloso**; os itens de planejamento da autoavaliação do orientador e da avaliação da orientação pelo estudante servem de comparação.

### Removidos por decisão do usuário
Execução do projeto · Conflitos de interesse · Tutoria.

### Reclassificado
"Responsabilidade na formação e supervisão" → vira **Formação** (declaração do estudante, abaixo).

## Dimensão 2 — Originalidade (registrada)

### Elementos medidos de 0 a 10 no formulário da banca (e indicados pelo discente na submissão da defesa)
1. **Problema ou pergunta de pesquisa** — a pergunta ainda não foi respondida ou o tema é pouco explorado.
2. **Lacuna demonstrada** — o estado da arte mostra com clareza o que ainda não se sabe e onde o trabalho entra.
3. **Abordagem ou método** — método novo, combinação nova de métodos ou aplicação inédita de método conhecido.
4. **Dados** — dados novos (população, contexto, período ou região ainda não estudados).
5. **Resultados** — achados novos e não triviais.
6. **Contribuição teórica ou conceitual** — avanço, revisão ou proposta de modelo, teoria ou conceito.
7. **Produto ou inovação** — software, modelo computacional, protocolo, tecnologia ou processo novo (relevante sobretudo no mestrado profissional).
8. **Grau geral de originalidade** — no formulário **sigiloso**: juízo franco da banca (original, replicação aplicada ou revisão).

### Declaração do discente na submissão da defesa
- Onde está a contribuição original (pode marcar mais de um): problema · método · dados · resultados · teoria · produto.
- Em uma ou duas frases, qual é a contribuição original.
- As mesmas perguntas 1 a 7 da banca (regra do espelho do discente).

### Medidas automáticas (a decidir)
- **Produtos registrados** (patente, registro de software no INPI, produto técnico) informados pelo discente → pontuação 0–10.
- **Proximidade com trabalhos existentes** — resumo comparado aos resumos indexados no OpenAlex pela busca semântica do sistema; **experimental**, alerta para a banca, não nota.
- Similaridade de texto (plágio) não mede originalidade → fica em **Integridade e conformidade**.

### Qualificação × defesa
Na qualificação a banca avalia o **potencial** de originalidade (problema, lacuna, método proposto); na defesa, a originalidade **realizada** (dados, resultados, contribuição). A diferença mede a evolução.

### Estatística
Mesmo modelo da dimensão 1, mais:
- originalidade costuma ter a **menor concordância entre avaliadores** — acompanhar a correlação intraclasse;
- **aberto × sigiloso**: média dos elementos 1–7 comparada ao "grau geral" do sigiloso;
- **declarado × reconhecido**: o que o discente marcou e respondeu comparado às notas da banca nos mesmos elementos;
- **mestrado e doutorado separados** (a exigência de originalidade é diferente).

## Dimensão 3 — Relevância e impacto (registrada)

### Diferença em relação à Originalidade
| Dimensão | Pergunta central |
|---|---|
| Originalidade | É novo? |
| Relevância | Importa? Para quem? |
| Impacto | O que muda, ou já mudou? |

Variam de forma independente (uma replicação pode ser pouco original e muito relevante). Nenhuma pergunta desta dimensão trata de novidade.

### Elementos medidos de 0 a 10 no formulário da banca (e indicados pelo discente na submissão da defesa)
1. **Importância do problema** — tamanho e gravidade (população afetada, carga de doença, custo). *Relevância.*
2. **Pertinência regional e social** — necessidade da Bahia, do Nordeste ou de grupos vulneráveis. *Relevância.*
3. **Alinhamento a prioridades** — SUS, políticas públicas, ODS, agências de fomento. *Relevância.*
4. **Contribuição para a área** — quanto o resultado faz a área avançar. *Impacto.*
5. **Aplicabilidade** — pode ser usado por serviços, gestores ou profissionais. *Impacto.*
6. **Potencial de influenciar decisões** — políticas públicas, protocolos, gestão. *Impacto.*
7. **Disseminação** — comunidade científica e sociedade (artigos, eventos, popularização). *Impacto.*
8. **Grau geral de relevância e impacto** — no formulário **sigiloso**.

### Tipo de impacto declarado pelo discente (já existe: "Impacto da Dissertação")
- 7 tipos — científico, metodológico, tecnológico, social, ambiental, formativo, institucional — cada um com "se aplica" (sim/não) e descrição.
- Passa a fazer parte da **submissão da defesa** (hoje pode ser preenchido a qualquer momento).
- A banca vê os tipos declarados ao avaliar os elementos 4 a 6 → comparação **tipo declarado × reconhecido**.
- É tipologia + descrição, **sem nota**; a nota da dimensão vem da banca (0–10).

### Evidências e medidas automáticas
- **Produção derivada** (OpenAlex + indicação do discente na defesa) → 0–10; apoia o elemento 7.
- **ODS do trabalho** (template LaTeX, `\odsSelecionadas`) → apoia o elemento 3.
- **Produtos técnicos em uso** — declaração do discente com comprovante (ex.: carta do serviço) → apoia o elemento 5.

### Tempo
Na defesa mede-se **relevância** e **impacto potencial**. O **impacto realizado** (citações, adoção, mudança de política) aparece anos depois — proposta de **acompanhamento posterior pela CPA** (2 e 5 anos após a defesa, citações no OpenAlex), em campo separado, sem alterar a nota da defesa.

### Qualificação × defesa
Qualificação: elementos 1 a 3 (relevância do problema). Defesa: todos.

### Estatística
Mesmo modelo, mais: mestrado acadêmico × profissional separados (no profissional a aplicabilidade tende a pesar mais); relevância (1–3) × impacto (4–7); produção derivada × nota da banca; declarado × reconhecido.

## Dimensão 4 — Produção associada (registrada)

Avalia os **produtos derivados da dissertação**. **Não é julgada pela banca**: o **estudante informa**, a **banca vê** as informações e a **nota é calculada pelo sistema** (0–10) pelas regras abaixo. Valores das tabelas são proposta inicial.

### A. Tipo e canal de publicação
| Tipo | Canal | Pontos |
|---|---|---|
| Artigo em periódico | revista sem fator de impacto | 5 |
| | 0 < FI ≤ 3 | 8 |
| | FI > 3 | 10 |
| Livro | editora universitária ou regional | 6 |
| | editora comercial nacional com conselho editorial | 7 |
| | editora internacional | 9 |
| Capítulo de livro | editora universitária ou regional | 4 |
| | editora comercial nacional com conselho editorial | 5 |
| | editora internacional | 7 |
| Trabalho em anais | evento local ou regional | 2 |
| | evento nacional | 3 |
| | evento internacional | 4 |

**Fator de impacto (decidido: opção b):** o sistema usa o indicador gratuito do OpenAlex — média de citações da revista nos últimos 2 anos (mesmo cálculo do fator de impacto), buscado automaticamente pelo ISSN/DOI.

### B. Repercussão na comunidade
- Citações de cada produto com DOI, pelo OpenAlex.
- Downloads/visualizações: do repositório (Zenodo, Figshare, institucional) ou informados pelo estudante com comprovante.
- Pontos: 0 citações → 0; 1–2 → 4; 3–9 → 7; 10 ou mais → 10; downloads como bônus limitado.
- Atualizado também no acompanhamento posterior (2 e 5 anos).

### C. Impacto da atividade de extensão — cadastro
Tipo de ação (curso, oficina, evento, prestação de serviço, projeto) · público atingido (nº de pessoas) · comunidade ou parceiro · carga horária · período · nº de registro na pró-reitoria de extensão · produto gerado/entregue ao parceiro · evidência (certificado, link, declaração do parceiro).
Pontos: faixas de público + faixas de carga horária + registro institucional (sim/não) + produto entregue (sim/não), limitado a 10.

### D. Impacto da popularização das ciências — cadastro
Tipo (palestra aberta, feira de ciências, imprensa, vídeo, podcast, rede social, material didático) · veículo ou local · alcance (público presente, visualizações, tiragem) · data · link ou evidência.
Pontos: faixas de alcance (até 50, até 500, até 5.000, acima de 5.000) + tipo de veículo (imprensa e mídia de massa valem mais), limitado a 10.

### Nota da dimensão
Média ponderada dos 4 blocos. **Ponderação padrão:** publicações **50%** · repercussão **20%** · extensão **15%** · popularização **15%**. **A CPA pode alterar esses percentuais** (configuração da CPA, com registro de quem alterou e quando; a soma deve ser 100%).

### Estatística
Distribuição dos tipos de produção por ano e linha; % de dissertações com pelo menos um artigo; produção × notas das outras dimensões; mestrado e doutorado separados; bolsista × não bolsista (quando a bolsa for registrada).

## Dimensão 5 — Autonomia do estudante (registrada)

Avalia o **estudante como pesquisador** (domínio, decisão própria, amadurecimento), não o trabalho.

### Elementos medidos de 0 a 10 no formulário da banca (e indicados pelo discente na submissão da defesa)
| # | Elemento | O que a banca avalia | Formulário |
|---|---|---|---|
| 1 | Domínio do tema | conhece a literatura e os conceitos; responde com segurança na arguição | aberto |
| 2 | Domínio do método | explica e justifica as escolhas metodológicas | aberto |
| 3 | Capacidade crítica | reconhece limitações e discute alternativas | aberto |
| 4 | Independência nas decisões | as decisões de pesquisa parecem dele, não só do orientador | sigiloso |
| 5 | Resposta às críticas da qualificação | incorporou, ou justificou não incorporar, as recomendações | aberto (só defesa) |
| 6 | Amadurecimento | evolução como pesquisador desde a qualificação | sigiloso (só defesa) |
| 7 | Grau geral de autonomia | juízo franco da banca | sigiloso |

Qualificação: elementos 1–4 e 7 (linha de base). Defesa: todos.

### Recomendações da qualificação (confirmado)
1. Na qualificação, a banca registra as recomendações (uma por linha).
2. Na submissão da defesa, o estudante responde cada uma: atendida · atendida em parte · não atendida (com justificativa), indicando onde está no texto.
3. Na defesa, a banca vê a lista ao avaliar o elemento 5.
Medida automática de apoio: % de recomendações atendidas (0–10).

### Avaliação do orientando pelo orientador (decidido)
- Formulário **próprio**, em que o orientador avalia o orientando (elementos 1–4 e 6).
- Pedido **só na defesa**, **junto com a autoavaliação do orientador**.
- **Não conta** para o resultado da qualificação nem da defesa — é dado para a CPA (comparação estudante × banca × orientador).
- Privado à CPA, como a autoavaliação.

### Medidas automáticas
Evolução (nota da dimensão na defesa − na qualificação, elementos 1–4) e % de recomendações atendidas.

### Estatística
Mesmo modelo, mais: aberto (1–3) × sigiloso (4, 6, 7); evolução qualificação → defesa; recomendações atendidas × nota da defesa; **estudante × banca × orientador**.

## Dimensão 6 — Aderência ao programa (registrada)

### O que já existe
Área de concentração e linha de pesquisa com **objetivo** descrito (linha também com ODS); linha declarada no cadastro do estudante; verificação de aderência por IA (Ollama) do trabalho à linha (alimenta o indicador A17). **Não existe:** cadastro de projetos de pesquisa.

### Elementos medidos de 0 a 10 no formulário da banca (e indicados pelo discente na submissão da defesa)
| # | Elemento | O que a banca avalia | Formulário |
|---|---|---|---|
| 1 | Aderência à área de concentração | enquadra-se no objetivo da área | aberto |
| 2 | Aderência à linha de pesquisa | tema e método correspondem ao objetivo da linha | aberto |
| 3 | Identidade do programa | usa de fato modelagem e simulação aplicadas a biossistemas | aberto |
| 4 | Vínculo com projeto de pesquisa | ligado a um projeto do orientador | aberto |
| 5 | Coerência com o curso | acadêmico: contribuição científica; profissional: produto ou solução aplicada | aberto |
| 6 | Grau geral de aderência | juízo franco da banca | sigiloso |

Qualificação e defesa: todos os elementos (a diferença mostra se o trabalho se afastou da linha).

### Declaração do discente (espelho, na submissão da defesa)
Confirma linha e área; descreve em 1–2 frases **como o trabalho usa modelagem e simulação**; responde às perguntas 1–5.

### Projetos de pesquisa (decidido)
- A **Secretaria cadastra os projetos dos docentes permanentes**, como no Lattes: **início, título, descrição** (proposta: também **fim**/"em andamento" e **situação** ativo/concluído, para não oferecer projeto encerrado).
- O **estudante é vinculado ao projeto no mesmo momento em que a Secretaria registra o orientador** (edição do discente em Usuários); na **troca de orientador**, o projeto também é trocado.

### Aderência ao projeto calculada pelo sistema (decidido: viável; na qualificação e na defesa)
- Entrada: título, resumo e palavras-chave extraídos do LaTeX × título e descrição do projeto vinculado.
- **Similaridade semântica** (embeddings `nomic-embed-text`, já usados no sistema): número estável.
- **Nota 0–10 com justificativa** pelo Ollama, com **régua fixa** (0 sem relação · 4 tangencial · 7 aderente com desvios · 10 plenamente dentro do projeto), citando trechos dos dois textos.
- Confiabilidade: temperatura zero, **3 execuções e mediana**; guarda modelo, versão da régua e data.
- **Apoia a banca** no elemento 4 (mostra nota e justificativa); **não decide**.
- Calibração: a CPA compara nota automática × nota da banca e ajusta a régua se divergirem muito.

### Outras medidas automáticas
- Aderência por IA à linha (já existe) → 0–10 (aderente 10, parcial 5, não aderente 0).
- Coerência do cadastro: linha do estudante = linha de atuação do orientador.
- ODS do trabalho (template) × ODS da linha.

### Estatística
Mesmo modelo, mais: banca × IA (concordância); % de aderentes por linha e equilíbrio entre linhas; aderência × outras dimensões.

## Bolsa do estudante (planejado — implementar depois)

Hoje o sistema **não registra** bolsa (só há o texto livre `\Financiamento` no template LaTeX, não extraído).

Tabela nova que **vincula o estudante à bolsa** (um estudante pode ter mais de uma bolsa ao longo do curso):

| Campo | Descrição |
|---|---|
| vínculo do discente no programa | a quem a bolsa pertence |
| agência | CAPES, CNPq, FAPESB, institucional, outra |
| modalidade | ex.: mestrado, doutorado, doutorado-sanduíche |
| nº do processo/termo | opcional |
| início e fim | período da bolsa |
| comprovante | arquivo (termo de concessão), opcional |
| registrado por / em | quem cadastrou e quando |

## Declarações do estudante

### A. Ao enviar o projeto (.zip) — declarações de submissão
Baseadas no que os periódicos da IEEE pedem na submissão, adaptadas ao PPGMSB (o estudante marca cada uma para poder enviar):

1. **Originalidade e exclusividade** — o trabalho é inédito, não foi apresentado como dissertação ou tese em outro programa e não está publicado na íntegra; é permitido ter versão em servidor de preprint e artigos derivados. *(Na IEEE: "submitted solely to this journal and is not published, in press, or submitted elsewhere, with exception that articles are permitted to be submitted to preprint servers".)*
2. **Conformidade com o template e as normas do programa** — o trabalho foi preparado de acordo com o template e as normas do PPGMSB. *(Na IEEE: "prepared in accordance with the journal's style and format requirements … Author Guidelines".)*
3. **Verificação prévia** — no PPGMSB, o papel da verificação prévia ("PaperPal Preflight" na IEEE) é feito pelo **próprio Katu**: compilação, extração dos elementos e conferência com o cadastro, antes do envio à homologação.
4. **Uso de inteligência artificial** — o uso de conteúdo gerado por IA (texto, figuras, imagens, código) deve ser **declarado nos agradecimentos**, identificando o sistema de IA usado, as seções em que foi usado e uma breve explicação do nível de uso. *(Na IEEE: "the use of content generated by artificial intelligence (AI) in an article … shall be disclosed in the acknowledgments section … The AI system used shall be identified, and specific sections … shall be identified and accompanied by a brief explanation regarding the level at which the AI system was used".)*

### B. Ao cadastrar a pesquisa — código, dados e ética (só marcar)
Vale para dissertação e tese. Modelo inspirado nas perguntas de submissão da IEEE (redação original entre parênteses) e na declaração de disponibilidade de dados da IEEE/Wiley/Nature.

**1. Código (software)** — *"Do you have code (software) associated with your manuscript?"*
- ☐ Não houve código próprio
- ☐ Sim — disponível em repositório público → **onde:** link (GitHub, GitLab, Zenodo…)
- ☐ Sim — disponível mediante solicitação ao autor
- ☐ Sim — não pode ser compartilhado → justificativa

**2. Dados** — *"Do you have data associated with your manuscript?"* — e **onde**
- ☐ Não se aplica (pesquisa teórica, sem dados)
- Que dados (pode marcar mais de um): ☐ primários coletados pelo estudante · ☐ secundários de bases públicas (ex.: DATASUS, IBGE) · ☐ secundários de acesso restrito · ☐ simulados/gerados por modelo
- Onde estão (uma): ☐ repositório público → **link ou DOI** (Zenodo, Figshare, repositório institucional…) · ☐ incluídos no trabalho (apêndice/suplementar) · ☐ mediante solicitação ao autor · ☐ não podem ser compartilhados → justificativa (sigilo, privacidade, contrato)

**3. Seres humanos** — *"Informed Consent" / "No, there were no human subjects"*
- ☐ Não, a pesquisa não envolveu seres humanos
- ☐ Sim, com aprovação do Comitê de Ética em Pesquisa → **CAAE** (Plataforma Brasil) e **nº do parecer** + ☐ houve Termo de Consentimento Livre e Esclarecido (ou dispensa justificada)

**4. Animais** — *"Animal Subjects"*
- ☐ Não, a pesquisa não envolveu animais
- ☐ Sim, com aprovação da Comissão de Ética no Uso de Animais → **nº do protocolo**

Regras: código e dados com "repositório público" exigem o link; "Sim" em seres humanos exige CAAE e nº do parecer; "Sim" em animais exige o nº do protocolo. Esses números aparecem para a banca (apoiam os elementos *Cuidado* e *Respeito* do Rigor científico) e para a CPA.

### C. Na proposta de defesa — Formação (cumpriu / não cumpriu)
1. Atividades Programadas Inter-Linhas
2. Proficiência em Língua Estrangeira
3. Publicação de Produção Científica
4. Atividade Extensionista
5. Popularização das Ciências
6. Exame de Qualificação (o sistema já sabe — pode vir preenchido)
7. Tirocínio Docente
8. Estágio Docência

Mais as **publicações** do estudante, buscadas no OpenAlex, com opção de indicar as que não estiverem lá.

### D. No ato da defesa (mestrado ou doutorado) — Publicação dos resultados
O estudante indica quais publicações saíram da dissertação.

## Perguntas em aberto

- [ ] Itens "cumpriu / não cumpriu" (Formação): convertem para 10/0 e entram na estatística, ou ficam como requisito sem nota?
- [ ] Formação entra em qual dimensão (provavelmente Processo formativo)?
- [ ] Peso das medidas automáticas na nota de cada dimensão.
- [ ] Redação oficial da declaração de dados da IEEE (usuário buscando).
- [ ] Espelho do discente: o discente dá **nota de 0 a 10** a cada pergunta (autoavaliação) ou **indica onde, no trabalho, está a evidência** (seção/página)?
- [ ] Acompanhamento posterior do impacto (2 e 5 anos após a defesa): entra?
- [ ] Bolsa: quem cadastra — Secretaria (dados oficiais) ou estudante (com comprovante)?
- [ ] Projetos: orientador com mais de um projeto ativo — a Secretaria escolhe ao registrar o orientador, ou o sistema vincula sozinho quando há um só e pede escolha quando há vários?
- [ ] Dimensões 7 e 8: a discutir.
- [ ] Perfil CPA (membros internos com número do ato; externos indicados pela CPA) — depois das dimensões.

## Referências usadas
- IEEE Author Portal — Data Availability Statement (documentação indisponível no momento da consulta): https://rex-docs.atypon.com/ieee-rex/admin/author_portal/c_auth_ethics-das.html
- Wiley — Data Sharing Policies: https://authors.aws.wiley.com/author-resources/Journal-Authors/open-access/data-sharing-citation/data-sharing-policy.html
- Nature — Data availability statements: https://nature.com/documents/nr-data-availability-statements-data-citations.pdf
- Diagrama de análises da CPA (rascunho): `docs/superpowers/specs/2026-10-29-cpa-analises-diagrama.md`
