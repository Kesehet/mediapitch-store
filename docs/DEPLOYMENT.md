# Production deployment

## Expected deployment sequence
1. Pull `main`.
2. Composer installs/updates the project.
3. Composer runs `database/composer-deploy.php`.
4. If the production DB is reachable, it runs base schema -> unapplied migrations -> non-destructive seed defaults -> bootstrap administrator sync.
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

## Administrator login / recovery
The administrator login uses an **email address**, not a separate username. To create or reset the deployment administrator, set:

```env
BOOTSTRAP_ADMIN_NAME="MediaPitch Admin"
BOOTSTRAP_ADMIN_EMAIL=your-admin@example.com
BOOTSTRAP_ADMIN_PASSWORD=your-password-at-least-8-characters
```

Then run:

```bash
composer deploy-db
```

When `BOOTSTRAP_ADMIN_PASSWORD` is non-empty, the deployment step synchronizes that account on every run: it creates the administrator if necessary, updates the configured email/name/password, re-enables the account and clears failed-login counters. Historical installs using `admin@mediapitch.in` are migrated to the configured email rather than silently creating a second bootstrap administrator.

After you can log in successfully, blank/remove `BOOTSTRAP_ADMIN_PASSWORD` from production `.env` if you want future deployments to stop resetting the administrator password. You can then manage/password-reset the account through the CMS normally.

A browser login throttle created before the password change is tied to the old password hash. Once deployment changes the password hash, that stale throttle is automatically discarded when the configured administrator tries the new credentials.

## Amazon Creators API refresh schedule
If Amazon integration is enabled, schedule a server-side refresh at least daily so non-offer Creators API content is refreshed within Amazon's documented 24-hour cache window. Price/offer data is treated more strictly by the application and is hidden after one hour.

Recommended cron example (adjust the PHP/Composer path for the host):

```cron
15 * * * * cd /path/to/mediapitch-store && /usr/bin/composer refresh-amazon --quiet >> /var/log/mediapitch-amazon-refresh.log 2>&1
```

Running hourly is preferred because offer data has a one-hour TTL. `composer refresh-amazon` only selects stale Amazon/hybrid products and batches GetItems calls safely. The command exits non-zero if refresh errors occur so hosting/cron monitoring can alert on failures.

For analytics retention, schedule:

```cron
35 3 * * * cd /path/to/mediapitch-store && /usr/bin/composer prune-analytics --quiet >> /var/log/mediapitch-prune.log 2>&1
```

## Automated smoke test
After deployment, run:

```bash
composer smoke-test
```

This is non-destructive. It checks PHP/extensions, production env basics, DB connectivity, required tables, migration tracking, an active user/admin presence and upload-directory writability. A non-zero exit code means at least one required check failed. GD is reported as a warning because the app can operate without it, but thumbnails/original-image optimization are reduced.

## Browser smoke test
After `composer smoke-test` passes:
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
