# Analytics privacy and retention

MediaPitch Store keeps analytics intentionally lightweight.

## What is stored

Affiliate click analytics stores the selected product and optional editorial context such as content ID, rank, CTA location, referrer, user agent and campaign. Search analytics stores the search phrase, optional category and result count. The search logger does not store visitor IP addresses or user-agent strings.

## Retention

Default retention is:

- Affiliate click analytics: 395 days
- Search analytics: 395 days
- Administrator audit history: 730 days

Configure these with `ANALYTICS_RETENTION_DAYS` and `AUDIT_RETENTION_DAYS`.

Run `composer prune-analytics` from cron (recommended weekly) to permanently delete records older than the configured windows.

## Data minimisation

Do not add visitor email, name, account identifiers or other directly identifying data to analytics tables. Campaign values should be marketing labels, not personal identifiers.

## Bot and staff traffic

Known automated user agents and explicitly configured internal IPs should be excluded from affiliate reporting. Configure internal addresses using `AFFILIATE_ANALYTICS_EXCLUDE_IPS` as a comma-separated list. This is a reporting/data-quality control, not a security boundary.

## Backups

Database backups may retain deleted analytics until the backup itself expires. Backup retention should therefore be no longer than operationally necessary and should follow the production backup policy.
