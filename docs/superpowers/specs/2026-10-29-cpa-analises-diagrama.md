# CPA — análises da qualidade das dissertações (rascunho para discussão)

Diagrama editável: cada caixa é uma linha `ID["texto"]`; cada seta é `ORIGEM --> DESTINO`.
Para remover uma análise, apague a linha da caixa e as setas que usam o ID dela; para incluir, crie uma caixa nova e ligue com setas.
Visualização: extensão "Markdown Preview Mermaid Support" no VS Code, ou cole o bloco em https://mermaid.live.

```mermaid
flowchart LR

    %% ───────────── 1. FONTES DE DADOS (já existem no Katu) ─────────────
    subgraph FONTES["Fontes de dados por dissertação"]
        direction TB
        F1["Barema padrão<br/>nota 0–10 por item e seção<br/>(cada avaliador, qualificação e defesa)"]
        F2["Barema confidencial<br/>nota 0–10 por item e seção<br/>(sigiloso — só CPA)"]
        F3["Autoavaliação do orientador<br/>15 afirmações em 5 dimensões<br/>(defesa — só CPA)"]
        F4["IEO<br/>estudante avalia a orientação<br/>(Likert, 6 categorias)"]
        F5["Composição da banca B1–B8<br/>externos, titulação, h-index,<br/>produção (ORCID/OpenAlex)"]
        F6["Referências do trabalho<br/>localizadas, atualidade (2 anos),<br/>produção do orientador citada"]
        F7["Prazos e trâmite<br/>ingresso → qualificação → defesa,<br/>devoluções na homologação"]
        F8["Resultado da banca<br/>aprovado / com modificações / reprovado"]
    end

    %% ───────────── 2. ANÁLISES ─────────────
    subgraph DISS["Análises — dissertação"]
        direction TB
        D1["Nota consolidada<br/>média padrão · confidencial · completa"]
        D2["Perfil por seção do barema<br/>pontos fortes e fracos"]
        D3["Concordância entre avaliadores<br/>desvio das notas na banca"]
        D4["Padrão × confidencial<br/>ressalvas não ditas em público"]
        D5["Evolução qualificação → defesa"]
    end

    subgraph ORI["Análises — orientador (credenciamento)"]
        direction TB
        O1["Desempenho dos orientandos<br/>médias, concluídas, no prazo"]
        O2["Autoavaliação × resultado da banca<br/>percepção × avaliação externa"]
        O3["Autoavaliação × IEO<br/>visão do orientador × do estudante"]
        O4["Tempo até a defesa<br/>e devoluções na homologação"]
    end

    subgraph PROG["Análises — programa"]
        direction TB
        P1["Distribuição das notas<br/>por ano, linha, curso"]
        P2["Itens do barema mais fracos<br/>no programa todo"]
        P3["Atualidade das referências × nota"]
        P4["Qualidade da banca (B1–B8) × nota"]
    end

    %% ───────────── 3. INDICADOR DE QUALIDADE ─────────────
    subgraph IND["Indicador de qualidade da dissertação"]
        direction TB
        I1["(a) Faixas fixas da nota completa<br/>≥9 excelente · 7–9 bom · 5–7 regular · &lt;5 insuficiente"]
        I2["(b) Índice composto ponderado<br/>pesos definidos pela CPA (SWARA)<br/>ranqueamento (MABAC)"]
        I3["(c) Parecer da CPA<br/>classificação final com justificativa, em ata"]
    end

    %% ───────────── 4. USOS ─────────────
    subgraph USO["Decisões da CPA"]
        direction TB
        U1["Nível de qualidade das<br/>dissertações do PPGMSB"]
        U2["Credenciamento · recredenciamento ·<br/>descredenciamento de docentes"]
        U3["Ações de melhoria da formação<br/>(disciplinas, orientação, normas)"]
        U4["Relatório para a autoavaliação<br/>do programa (CAPES)"]
    end

    %% ───────────── LIGAÇÕES ─────────────
    F1 --> D1 & D2 & D3 & D5
    F2 --> D1 & D4
    F8 --> D1
    F1 --> D4

    F1 & F2 --> O1
    F7 --> O1 & O4
    F3 --> O2 & O3
    F2 --> O2
    F4 --> O3

    D1 --> P1
    D2 --> P2
    F6 --> P3
    F5 --> P4
    D1 --> P3 & P4

    D1 --> I1
    D1 & D3 & D4 --> I2
    F6 & F7 --> I2
    I1 & I2 --> I3

    I3 --> U1
    O1 & O2 & O3 & O4 --> U2
    I3 --> U2
    P1 & P2 & P3 & P4 --> U3
    U1 & U2 & U3 --> U4

    %% ───────────── CORES (opcional) ─────────────
    classDef fonte fill:#e8f1f3,stroke:#00303A,color:#00303A
    classDef analise fill:#fff7e6,stroke:#b45309,color:#3b2a06
    classDef indicador fill:#eef2ff,stroke:#4338ca,color:#1e1b4b
    classDef uso fill:#ecfdf5,stroke:#047857,color:#064e3b
    class F1,F2,F3,F4,F5,F6,F7,F8 fonte
    class D1,D2,D3,D4,D5,O1,O2,O3,O4,P1,P2,P3,P4 analise
    class I1,I2,I3 indicador
    class U1,U2,U3,U4 uso
```

## Pontos em aberto (para a discussão)

- [ ] Qual caminho para o indicador: (a), (b), (c) ou combinação (proposta: b + c).
- [ ] Se (b): quais componentes entram no índice e quem define os pesos.
- [ ] Nas notas confidenciais, a CPA vê o nome do avaliador ao lado da nota?
- [ ] Período de análise: por ano, por quadriênio CAPES, por turma de ingresso?
- [ ] Membro externo da CPA: como ganha acesso (convite por e-mail, cadastro pela Secretaria)?
