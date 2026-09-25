# Katu — Academic Quality & Self-Assessment Platform

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.21011690.svg)](https://doi.org/10.5281/zenodo.21011690)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net)

Sistema web para gerenciamento do ciclo completo de qualificações e defesas de dissertações/teses do Programa de Pós-Graduação em Modelagem em Saúde da Bahia (PPGMSB), vinculado à Universidade do Estado da Bahia (UNEB). Incorpora métodos de decisão multicritério (MABAC/SWARA), bibliometria automatizada via ORCID/OpenAlex, Índice de Excelência da Orientação (IEO) e relatórios de autoavaliação do PPG.

> **Cite como:** Figueredo, M. (2026). *Katu — Academic Quality & Self-Assessment Platform for Graduate Programs* (v1.0.0). Zenodo. [https://doi.org/10.5281/zenodo.21011690](https://doi.org/10.5281/zenodo.21011690)

---

## Stack tecnológica

| Camada | Tecnologia |
| --- | --- |
| Backend | PHP 8.2+ · Laravel 12 |
| Banco de dados | MySQL 8+ |
| Frontend (layout) | Tailwind CSS 3 · Alpine.js 3 |
| Frontend (componentes) | Bootstrap 5.3.3 · Bootstrap Icons 1.11.3 |
| Build | Vite 7 · Laravel Vite Plugin |
| Análise de PDF por IA | Ollama (modelo local) |
| E-mail | SMTP configurável (padrão: log em desenvolvimento) |
| Autenticação | Laravel Breeze |

---

## Requisitos

- PHP 8.2 ou superior com extensões: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `openssl`
- Composer 2.x
- Node.js 20+ e npm
- MySQL 8+
- Ollama rodando localmente (necessário para análise de PDF; opcional para as demais funcionalidades)

---

## Instalação

### 1. Clone e instale dependências

```bash
git clone <url-do-repositorio> defesa-ppgmsb
cd defesa-ppgmsb
composer install
npm install
```

### 2. Configure o ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Edite o `.env` com as configurações de banco, e-mail e Ollama (ver seção **Variáveis de ambiente** abaixo).

### 3. Banco de dados

Crie o banco no MySQL:

```sql
CREATE DATABASE defesa_ppgmsb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Execute as migrations:

```bash
php artisan migrate
```

> O comando `composer setup` executa os passos 1–3 automaticamente em ambiente de desenvolvimento.

### 4. Build dos assets

```bash
npm run build
```

Em desenvolvimento, use `npm run dev` (com hot-reload via Vite).

### 5. Inicie os serviços

```bash
# Servidor, fila de jobs, logs e Vite em paralelo:
composer dev
```

Ou separadamente:

```bash
php artisan serve
php artisan queue:listen --tries=1
```

---

## Variáveis de ambiente

### Aplicação

```env
APP_NAME=Katu
APP_ENV=production          # local | production
APP_KEY=                    # gerado automaticamente
APP_DEBUG=false             # true somente em local
APP_URL=https://seu-dominio.uneb.br
APP_LOCALE=pt_BR
```

### Banco de dados

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=defesa_ppgmsb
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### E-mail

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.seu-servidor.com.br
MAIL_PORT=587
MAIL_USERNAME=noreply@ppgmsb.uneb.br
MAIL_PASSWORD=sua_senha_smtp
MAIL_FROM_ADDRESS=noreply@ppgmsb.uneb.br
MAIL_FROM_NAME="PPGMSB/UNEB"
```

> Em desenvolvimento, use `MAIL_MAILER=log` para gravar e-mails em `storage/logs/laravel.log` sem envio real.

### Filas

```env
QUEUE_CONNECTION=database   # jobs processados pelo banco; use redis em produção para melhor desempenho
```

### Ollama (análise de PDF por IA)

```env
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5:7b     # modelo instalado localmente
OLLAMA_TIMEOUT=120
```

> Instale o Ollama em <https://ollama.com> e baixe o modelo: `ollama pull qwen2.5:7b`

### Armazenamento de arquivos

```env
FILESYSTEM_DISK=local       # em produção, considere "s3" com bucket privado
```

Os PDFs enviados pelos discentes ficam em `storage/app/private/`.

---

## Papéis de usuário

O campo `categoria` na tabela `users` controla o acesso:

| Valor | Papel | Acesso |
| --- | --- | --- |
| `sec` | Secretaria | Gestão completa: programas, usuários, bancas, baremas, relatórios, ata |
| `docente` | Docente (avaliador / orientador) | Dashboard de bancas, formulários de avaliação, finalização (orientador), ata (orientador) |
| `discente` | Discente | Dados pessoais, acompanhamento de bancas, envio de documentos |
| `admin` | Administrador | Reservado para uso futuro — não há lógica de autorização implementada atualmente |

O primeiro acesso de cada usuário é feito via link enviado por e-mail pela secretaria, que exige troca imediata de senha.

---

## Módulos do sistema

### Gestão acadêmica (secretaria)

- **Instituições**: dados da UNEB e instituições parceiras (nome, departamento)
- **Programas**: PPGMSB com níveis mestrado/doutorado
- **Áreas de concentração e linhas de pesquisa**
- **Usuários**: cadastro de secretaria, docentes e discentes com envio de acesso por e-mail
- **Vínculos**: associação docente ↔ programa; discente ↔ programa + linha de pesquisa + matrícula
- **Orientações**: definição de orientador e coorientadores por trabalho acadêmico
- **Membros externos**: convite por link, cadastro público, diploma de doutorado em armazenamento privado e validação pela secretaria
- **Critérios da CPA**: requisitos versionados por etapa, nível e tipo de evidência
- **Homologação**: fila de bancas, conferência de evidências, aprovação ou devolução com justificativa

### Baremas (instrumentos de avaliação)

Configurados pela secretaria, os baremas definem os critérios de avaliação das bancas:

- **Tipo**: `qualificacao` | `defesa` | `qualificacao_confidencial` | `defesa_confidencial`
- **Nível**: `mestrado` | `doutorado` | `ambos`
- **Estrutura**: seções → itens (cada item com `nota_maxima`, `peso`, flag `obrigatorio`)

> O barema confidencial é preenchido individualmente por cada avaliador e não é compartilhado com os demais membros da banca.

A nota final por avaliador por barema é calculada como:

```text
nota_final = Σ(nota_item × peso_item) / Σ(nota_maxima_item × peso_item) × 10
```

### Bancas

Cada trabalho acadêmico pode ter uma banca de qualificação e uma de defesa. A banca de defesa só é liberada após a qualificação ser finalizada com aprovação.

**Membros da banca** (`banca_membros`): armazena os **avaliadores** com papel `avaliador`. O orientador é presidente da banca por meio do vínculo de orientação — não entra como membro avaliador.

**Ciclo de vida da banca** (campo `status`):

```text
cadastrada → em_avaliacao → finalizada
                    ↑
          (secretaria pode reabrir)
```

Além do status de avaliação, a banca possui o ciclo de homologação da Secretaria:

```text
aguardando → homologada → em_avaliacao → finalizada
      ↓
  devolvida → reenviada
```

Uma banca devolvida não libera o envio do trabalho. A homologação só é concluída quando as evidências obrigatórias ativas da CPA estiverem validadas ou justificadamente marcadas como não aplicáveis.

### Avaliação (docentes)

Fluxo de avaliação:

1. Docente acessa o dashboard de avaliações e vê as bancas onde é avaliador
2. Para cada barema disponível, preenche um formulário com notas por item
3. Pode salvar como **rascunho** a qualquer momento (sem calcular nota)
4. Ao **enviar**, a nota final é calculada, `enviada_em` é registrado e o campo fica bloqueado
5. Quando **todos os avaliadores** enviaram **todos os baremas**, o orientador recebe e-mail de notificação
6. O orientador acessa a banca, confere o progresso e **finaliza** registrando o resultado
7. O discente recebe e-mail com resultado e nota média

**Resultados possíveis**: `aprovado` | `aprovado_modificacoes` | `reprovado`

**Nota média da banca**: média aritmética das `nota_final` de todos os avaliadores.

### Ciclo de vida do trabalho acadêmico (campo `status` em `trabalho_academicos`)

```text
em_andamento → qualificado → defendido → concluido
      ↓
   cancelado
```

- Qualificação aprovada (ou aprovada c/ modificações) → `qualificado`
- Defesa aprovada (ou aprovada c/ modificações) → `defendido`
- Reprovado → status permanece inalterado

### Documentos (discentes)

- Discente envia PDF do trabalho para análise prévia
- O sistema extrai texto com `smalot/pdfparser`
- Análise por IA (Ollama) verifica estrutura, normas ABNT e gera checklist
- A secretaria pode liberar ou solicitar reenvio
- Aprovado: discente pode encaminhar para a banca

### Ata da banca

Documento HTML imprimível (via `Ctrl+P` → salvar como PDF) no formato PPGMSB, gerado após finalização. Contém:

- Cabeçalho institucional com logos `public/images/logoSemFundo.png` e `public/images/logo_uneb.png`
- Texto completo da sessão pública de avaliação
- Resultado pré-marcado conforme o registro no sistema
- Grade de assinaturas compatível com o portal **gov.br** (assinador.iti.br)
- Código de controle gerado a partir do ID e data de criação da banca

**Acesso**: apenas o orientador (presidente da banca) e a secretaria.

---

## E-mails automáticos

| Gatilho | Destinatário | Classe Mail |
| --- | --- | --- |
| Novo usuário criado pela secretaria | Usuário cadastrado | `PrimeiroAcessoUsuarioMail` |
| Banca cadastrada | Avaliadores da banca | `BancaCadastradaMail` |
| Discente envia trabalho para a banca | Avaliadores da banca | `TrabalhoEnviadoBancaMail` |
| Todos os avaliadores enviaram avaliações | Orientador | `BancaProntaParaFinalizarMail` |
| Banca finalizada | Discente | `BancaFinalizadaDiscenteMail` |
| Solicitação de recuperação de senha | Usuário solicitante | `RecuperacaoSenhaTemporariaMail` |

---

## Estrutura de arquivos relevante

```text
app/
├── Http/Controllers/
│   ├── AvaliacaoController.php         # avaliação docente, finalização, ata
│   ├── BancaController.php             # CRUD bancas + reabrir
│   ├── BaremaController.php            # CRUD baremas
│   ├── DashboardController.php         # redireciona por categoria de usuário
│   ├── TrabalhoDocumentoController.php # upload + análise de PDF
│   └── UsuarioController.php           # cadastro com envio de e-mail de acesso
├── Mail/
│   ├── BancaFinalizadaDiscenteMail.php
│   └── BancaProntaParaFinalizarMail.php
└── Models/
    ├── Banca.php                        # resultadoLabel(), avaliadores(), avaliacoes()
    ├── BancaAvaliacao.php               # notaCalculada()
    ├── BancaMembro.php
    ├── BancaAvaliacaoNota.php
    ├── Barema.php / BaremaSecao.php / BaremaItem.php
    ├── DiscenteProgramaVinculo.php
    ├── DocenteProgramaVinculo.php
    ├── Orientacao.php
    └── TrabalhoAcademico.php

resources/views/
├── avaliacoes/
│   ├── banca.blade.php                 # detalhe da banca para docente
│   ├── form.blade.php                  # formulário de avaliação por barema
│   ├── ata.blade.php                   # ata oficial (HTML imprimível)
│   ├── relatorio-individual.blade.php
│   └── relatorio-consolidado.blade.php
├── bancas/
│   ├── index.blade.php                 # lista de bancas (secretaria)
│   └── edit.blade.php
├── dashboards/
│   ├── docente.blade.php
│   ├── discente.blade.php
│   └── secretaria.blade.php
└── emails/
    ├── banca-finalizada-discente.blade.php
    └── banca-pronta-para-finalizar.blade.php

public/images/
├── logoSemFundo.png                    # logo PPGMSB (usada na Ata)
└── logo_uneb.png                       # logo UNEB (usada na Ata)
```

---

## Tarefas de manutenção comuns

### Adicionar novo programa

1. Acesse **Secretaria → Programas → Novo programa**
2. Crie as áreas de concentração e linhas de pesquisa
3. Cadastre os baremas de qualificação e defesa para o programa (todos os tipos necessários)

### Cadastrar novo docente ou discente

1. **Usuários → Novo usuário** com a categoria correta
2. O sistema envia e-mail de primeiro acesso automaticamente
3. Para docentes: vincule ao programa em **Usuários → Editar → Vínculos**
4. Para discentes: defina linha de pesquisa e matrícula no vínculo

### Adicionar ou atualizar barema

1. **Baremas → Novo barema** — defina tipo, nível e programa
2. Adicione seções e itens com `nota_maxima`, `peso` e flag `obrigatorio`
3. Ative o barema para que apareça automaticamente nas bancas do programa

> Baremas inativados não afetam avaliações já existentes — apenas deixam de aparecer para novas bancas.

### Reabrir banca para correção

Na listagem de bancas (**Secretaria → Bancas**), clique em **Reabrir** ao lado da banca finalizada. Os avaliadores poderão editar e reenviar suas avaliações. O orientador deverá finalizar novamente após as correções.

### Atualizar logos da Ata

Substitua os arquivos em `public/images/logoSemFundo.png` e `public/images/logo_uneb.png` mantendo os mesmos nomes. A Ata é gerada em tempo real — não há cache de imagem.

---

## Comandos úteis

```bash
# Limpar todos os caches (após mudanças de config, rotas ou views)
php artisan optimize:clear

# Rodar migrations em produção
php artisan migrate --force

# Verificar fila de jobs pendentes
php artisan queue:work --once

# Abrir Tinker para inspeção interativa do banco
php artisan tinker
```

---

## Segurança em produção

- Defina `APP_DEBUG=false` e `APP_ENV=production`
- Configure `SESSION_SECURE_COOKIE=true` se usar HTTPS (recomendado)
- Certifique-se de que `storage/` e `bootstrap/cache/` têm permissão de escrita pelo servidor web
- O diretório `storage/app/private/` armazena PDFs dos discentes — **não deve ser acessível publicamente**
- Gere um `APP_KEY` único por instalação com `php artisan key:generate`
