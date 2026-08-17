# Coding and release conventions

## PHP
- Target PHP 8.2+.
- Use `declare(strict_types=1);` in PHP classes/scripts.
- Use prepared statements for dynamic SQL.
- Escape plain-text public output with `e()`.
- Use `safe_html()` / `editorial_html()` only for intended rich editorial content.
- All admin writes require CSRF validation and an explicit role capability.
- Never log passwords, reset tokens, access tokens or Amazon credential secrets.
- Prefer small controllers/repositories/services over adding more responsibilities to `AdminController`.

## Database
- Add schema changes as numbered files in `database/migrations/`.
- Migrations must be safe to retry because MariaDB/MySQL DDL may implicitly commit.
- Do not edit an already-applied migration to change production state; add a new migration.
- Seeds must be non-destructive and must not overwrite CMS edits.

## Git
- `main` is currently the production branch.
- Commit messages should describe one meaningful change in imperative form.
- Do not force-push `main`.
- Once rapid-build mode ends, use short-lived feature branches + PRs for non-trivial changes.

## Releases
Use semantic-style tags when formal releases begin:
- `v0.x.y` while the CMS is still pre-1.0
- patch: fixes/hardening
- minor: backwards-compatible features
- major: intentionally incompatible release

Before tagging a release:
1. PHP syntax workflow green.
2. Production DB backup taken for schema-heavy releases.
3. `composer deploy-db` tested against staging/backup copy when possible.
4. Smoke-test checklist completed.
