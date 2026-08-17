# Backup, rollback and recovery

## Database backup before risky releases
Create a logical backup before schema-heavy releases:

```bash
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 -u USER -p DATABASE > mediapitch-store-YYYYMMDD-HHMM.sql
```

Store backups outside the web root and restrict access.

## Restore
Restore into the intended database only after confirming the target:

```bash
mysql -u USER -p DATABASE < mediapitch-store-YYYYMMDD-HHMM.sql
```

## Code rollback
1. Identify the last known-good commit on `main`.
2. Prefer a Git revert commit over rewriting production branch history.
3. Redeploy.
4. Run `/health` and the smoke-test checklist in `docs/DEPLOYMENT.md`.

## Database rollback rule
Migrations are forward-oriented. Do not automatically reverse DDL in production unless a tested down-migration exists. If a release changes data/schema incompatibly, restore the pre-release DB backup together with the matching code revision.

## Uploads
`public/uploads/` is application data and is not recreated by Git. Back it up separately or move media to object storage before relying on stateless deployments.

## Recovery checklist
- Database restored/available
- `public/uploads/` available
- `.env` and `APP_KEY` restored
- `/health` green
- Admin login works
- Public product/category/article pages load
- Affiliate redirects work
