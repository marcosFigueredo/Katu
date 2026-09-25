# Operação do fluxo da Secretaria

## Primeira configuração

1. Configure `.env` com MySQL, URL pública e e-mail.
2. Gere a chave da aplicação: `php artisan key:generate`.
3. Crie o banco `defesa_ppgmsb` no MySQL.
4. Execute `php artisan migrate --force`.
5. Limpe caches: `php artisan optimize:clear`.
6. Gere os assets: `npm install` e `npm run build`.

Os diplomas e documentos ficam no disco local privado (`storage/app/private`) e não precisam de `storage:link`. O link simbólico é necessário apenas se algum recurso público passar a usar `storage/app/public`.

## Desenvolvimento local

Em terminais separados:

```powershell
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Para e-mail sem envio externo, mantenha `MAIL_MAILER=log` e consulte `storage/logs/laravel.log`. Para produção, configure SMTP e use Redis ou outro worker persistente para a fila.

## Rotina da Secretaria

1. Cadastre o programa, áreas, linhas, estudantes e docentes.
2. Acesse **Orientações** e atribua o orientador/coorientadores ao vínculo acadêmico do discente.
3. Acesse **Membros externos**, envie o convite e acompanhe o cadastro.
4. Abra o diploma recebido, devolva com observação quando necessário ou aprove o membro.
5. Acesse **Critérios da CPA** e mantenha uma versão ativa para qualificação e defesa.
6. Acesse **Homologação de bancas**, confira evidências e composição, depois homologue ou devolva com justificativa.
7. Somente bancas homologadas ficam liberadas para envio do trabalho e avaliação.

## Rotas de operação

- `membros-externos.index`: fila de convites e validações.
- `membros-externos.registro`: formulário público recebido pelo membro externo.
- `criterios-cpa.index`: configuração dos requisitos CPA.
- `bancas.homologacao`: fila de homologação e devolução.

## Verificação antes de disponibilizar

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan route:list --path=membros-externos
php artisan route:list --path=criterios-cpa
php artisan route:list --path=bancas
php artisan test --compact
npm run build
git diff --check
```

Em uma base de homologação descartável, valide todas as migrações e seeders com:

```powershell
php artisan migrate:fresh --seed --force
```

Não execute `migrate:fresh` na base de desenvolvimento ou produção: ele apaga todas as tabelas.
