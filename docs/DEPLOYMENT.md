# Production deployment

## Expected deployment sequence
1. Pull `main`.
2. Composer installs/updates the project.
3. Composer runs `database/composer-deploy.php`.
4. If the production DB is reachable, it runs base schema -> unapplied migrations -> non-destructive seed defaults.
5. If DB credentials are not available during the Composer phase, application deployment continues and DB deployment can be run explicitly with `composer deploy-db`.

## Required production environment
Set at minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://store.mediapitch.in
APP_KEY=<long-random-secret>
DB_HOST=<host>
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<password>
DB_CHARSET=utf8mb4
SESSION_SECURE_COOKIE=true
MAIL_TRANSPORT=mail
MAIL_FROM=admin@mediapitch.in
```

Never commit `.env` or production secrets.

## Post-deploy smoke test
1. `/health` returns HTTP 200 with `{"status":"ok","database":"ok"}`.
2. Homepage loads with CSS/JS.
3. `/admin/login` loads and remains on the production host after login.
4. Admin dashboard loads.
5. Create/edit an inactive test product, then archive it.
6. Open Media Library and verify upload permissions.
7. Open Blog/Guides/Reviews/Comparisons editors.
8. Confirm `/sitemap.xml` and `/robots.txt` load.
9. Confirm one existing `/go/{id}` affiliate link redirects correctly without editing the product.
10. If Amazon is enabled, run **Test Amazon authentication** before using import/refresh.

## When DB migration fails
Run:

```bash
composer deploy-db
```

The migration runner records applied files in `schema_migrations` and migrations are written to be retry-safe. Do not manually mark a migration applied unless its SQL has been verified in the database.

## Debugging
Temporarily set `APP_DEBUG=true` only while actively diagnosing a production error, then return it to `false`.
