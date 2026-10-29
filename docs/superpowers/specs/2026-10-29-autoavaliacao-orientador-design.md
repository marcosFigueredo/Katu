# Autoavaliação do orientador na banca de defesa — desenho

Data: 2026-10-29 · Branch: `feat/submissao-latex`

## Intenção

Na banca de **defesa**, o orientador (presidente da banca) faz uma **autoavaliação** da sua atuação na orientação, com um barema que a Secretaria cadastra junto com os demais. A autoavaliação é condição para o presidente finalizar a banca e liberar a ata e a folha de aprovação aos membros. Ao mesmo tempo, a tela da banca do orientador passa a mostrar só as referências do trabalho em que ele é autor.

Por quê: o programa quer registrar, a cada defesa, como o orientador avalia o próprio acompanhamento (planejamento, comunicação, formação científica, relação com o orientando, resultados). Hoje só existe o IEO, em que o **discente** avalia o orientador.

## Decisões do usuário

1. **Escala:** avaliação dos avaliadores e autoavaliação usam a mesma escala de **0 a 10** por item (como os baremas atuais). A conversão para outra escala (ex.: Likert) fica para depois — a nota é guardada de forma que isso seja possível.
2. **Barema:** cadastrado pela **Secretaria**, na mesma tela dos baremas de qualificação e defesa, como um tipo novo: **Autoavaliação do orientador**. Dimensões viram seções; afirmações viram itens.
3. **Só na defesa:** o bloco aparece apenas na banca de `tipo = defesa`. Na qualificação não existe.
4. **Preenchimento único:** o orientador abre, responde **todas** as perguntas e salva de uma vez. Não há rascunho; depois de salva, não muda.
5. **Posição na tela:** bloco **AUTOAVALIAÇÃO** na página da banca (`avaliacoes.banca`), logo **antes de "Status dos avaliadores"**.
6. **Trava:** sem a autoavaliação concluída, o presidente **não consegue finalizar a banca nem liberar a ata e a folha de aprovação**.
7. **Envio da ata e da folha aos membros é ação do presidente.** A Secretaria **visualiza** ata e folha, mas não envia.
8. **Quem vê o quê** (a CPA é um perfil que ainda não existe — é o próximo trabalho):
   - **Autoavaliação do orientador:** privada à **CPA**. Nem os avaliadores, nem a Secretaria, nem o discente veem as respostas.
   - **Avaliação da banca (barema padrão):** vista pelo **orientador** (presidente), com respostas, métricas e gráficos.
   - **Avaliação confidencial:** vista **apenas pela CPA** (além do próprio avaliador que a preencheu). O orientador e a Secretaria deixam de ver as respostas confidenciais.
9. **Sempre deve haver barema cadastrado para todas as ações.** Nenhuma etapa segue sem o barema ativo correspondente.
10. **Verificação das referências:** para o orientador, o relatório mostra **só as referências em que ele é autor** (as marcadas como "Produção do orientador").

## Comportamento

### Barema de autoavaliação (Secretaria)
- `baremas.tipo` ganha o valor `autoavaliacao_orientador` (rótulo "Autoavaliação do orientador") em criar, editar, listar e filtros. Migration altera o enum no MySQL e no SQLite dos testes.
- Mesmas regras dos outros baremas: programa da Secretaria, `nivel` (mestrado/doutorado/ambos), versão, ativo, seções com peso e itens com `nota_maxima` (padrão 10) e peso. Nota final pela mesma fórmula: `Σ(nota × peso) / Σ(nota_max × peso) × 10`.
- O barema de autoavaliação **nunca** entra na lista de baremas dos avaliadores (`baremasParaBanca` já filtra por `tipo`/`tipo_confidencial`; manter assim e cobrir com teste).

### Autoavaliação (orientador)
- Quem responde: o **orientador vigente** do trabalho (`orientacoes` com `tipo = orientador`, `ativo = true`). Coorientadores não respondem.
- Quando: banca de defesa **homologada** e ainda não finalizada.
- Qual barema: ativo, do programa do trabalho, `tipo = autoavaliacao_orientador`, `nivel` igual ao do trabalho ou `ambos`. Se houver mais de um ativo, usa o mais recente (maior `id`). *(Chamada minha.)* Sem barema ativo não há autoavaliação possível — e, pela regra 9, a defesa não chega a ser homologada nessa situação (ver "Barema obrigatório").
- Formulário: todas as seções e itens ativos; cada item com nota de 0 até `nota_maxima`, obrigatória; campo de observações gerais opcional. Ao salvar: valida tudo, grava as notas, calcula a nota final, registra `enviada_em`. Uma única autoavaliação por banca e orientador.
- Depois de enviada, o bloco mostra só "Autoavaliação concluída em {data}" — **sem** as respostas nem a nota, que são privadas à CPA. *(Chamada minha: nem o próprio orientador revê as respostas depois de enviar.)*

### Trava e envio da ata e da folha
- Na banca de **defesa**, "Finalizar" fica **desabilitado** com o aviso "Conclua sua autoavaliação para finalizar a banca e liberar a ata e a folha de aprovação" enquanto não houver autoavaliação enviada. A rota `avaliacoes.finalizar` também recusa (erro de validação), não só a tela.
- A ação de finalizar passa a ser **"Finalizar e enviar ata e folha aos membros"**: além do que já faz (resultado, nota média, e-mail ao discente), envia aos **avaliadores** da banca um e-mail novo (`AtaFolhaBancaMail`) com o link da ata e o PDF da folha de aprovação, e registra `bancas.ata_enviada_em`. Vale para qualificação e defesa; só a defesa exige a autoavaliação.
- Os botões "Ata da banca" e "Folha de aprovação" do orientador, antes da finalização, seguem a mesma trava na defesa (desabilitados até a autoavaliação). A rota da folha/ata recusa o orientador nessa situação.
- Depois do envio (`ata_enviada_em` preenchido), os **avaliadores** da banca também podem abrir a ata e a folha (hoje só orientador e Secretaria abrem).
- Secretaria: continua podendo **abrir** ata e folha (como hoje, sem trava); não há para ela botão de envio.

### Quem vê as respostas
- **Autoavaliação:** ninguém no sistema atual (fica para a CPA). Não aparece para avaliadores, Secretaria nem discente; o orientador vê só que concluiu.
- **Avaliação padrão (barema `qualificacao`/`defesa`):** o orientador continua vendo respostas, notas, relatório individual e consolidado, como hoje.
- **Avaliação confidencial (`*_confidencial`):** o orientador e a Secretaria **deixam de ver** as respostas e notas — sai dos relatórios consolidado e individual, e a rota do relatório individual de um barema confidencial recusa (403) quem não é o próprio avaliador. Na página da banca, o orientador continua vendo **se** cada avaliador já enviou a confidencial (status), mas não o conteúdo. A nota média da banca continua sendo calculada como hoje. O avaliador continua vendo a própria avaliação confidencial.

### Barema obrigatório
- Na homologação (Secretaria), entra uma verificação nova: o programa precisa ter barema **ativo** para cada formulário da etapa, no nível do trabalho — avaliação (`qualificacao` ou `defesa`), avaliação confidencial (`qualificacao_confidencial` ou `defesa_confidencial`) e, na defesa, `autoavaliacao_orientador`. Faltando algum, a homologação é recusada e a pendência aparece junto das demais ("Barema de … não cadastrado").
- `avaliacoes.finalizar` também recusa se faltar algum desses baremas (defesa homologada antes desta regra).

### Referências na tela do orientador
- Em `avaliacoes.banca`, quando quem acessa é o orientador, o relatório (`partials.relatorio-referencias`) lista **só** as referências com `do_orientador = true`, e o resumo do topo conta apenas essas (total, atualidade, não localizadas). Se não houver nenhuma, mostra "Nenhuma referência de sua autoria foi identificada".
- Avaliadores continuam vendo o relatório completo. Secretaria continua sem ver (regra atual).

### Dados de teste
- `CenarioTesteSeeder` cria o barema "Autoavaliação do orientador (teste)" com as **5 dimensões e 15 afirmações** enviadas pelo usuário, notas 0–10, peso 1, nível ambos, ativo.

## Fora do escopo
- Perfil **CPA** e a tela de respostas para a CPA (próximo trabalho).
- Conversão de escala (0–10 → Likert ou outra).
- Mudanças no IEO (avaliação do orientador pelo discente).
- Autoavaliação na qualificação ou pelos coorientadores.

## Anti-objetivos
- A Secretaria passar a ver respostas de avaliação ou autoavaliação.
- A autoavaliação aparecer como barema para os avaliadores ou entrar na nota média da banca.
- A autoavaliação ou a avaliação confidencial aparecerem para o orientador, a Secretaria ou o discente.

## Chamadas minhas (o usuário pode trocar)
- **Armazenamento:** tabelas próprias `autoavaliacoes_orientador` (banca, vínculo do orientador, barema, nota final, observações, `enviada_em`; única por banca + orientador) e `autoavaliacao_orientador_notas` (item, nota). Não reaproveito `banca_avaliacoes` porque ela exige `banca_membro_id` e o orientador não é membro.
- **Nota da autoavaliação não entra** na nota média da banca.
- **E-mail aos membros** leva o link da ata (HTML, exige login) e a folha em PDF anexa.

## Deixado para quem implementar
- Layout do bloco e do formulário, seguindo o padrão visual da página.
- Textos de ajuda e mensagens que não estão fixados acima.
