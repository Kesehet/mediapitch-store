# Local development

## Requirements
- PHP 8.2+
- PDO MySQL
- MariaDB/MySQL
- cURL for Amazon Creators API
- GD optional for WebP thumbnails
- Composer

## Setup
1. Copy `.env.example` to `.env`.
2. Set DB credentials and `APP_KEY`.
3. Create an empty database.
4. Run `composer install`.
5. Run `composer deploy-db` if the Composer hook could not reach the DB.
6. Start PHP locally from the repository root, for example `php -S 127.0.0.1:8000 -t public`.
7. Visit `/health` and `/admin/login`.

## Seed administrator
On an empty `users` table the deploy seed creates:
- Email: `admin@mediapitch.in`
- Temporary password: `Change me`

Change this password immediately through **My account**.

## Password-reset mail
- `MAIL_TRANSPORT=mail` uses PHP `mail()`.
- `MAIL_TRANSPORT=log` writes the reset link to the PHP error log for local testing only.
- `PASSWORD_RESET_TTL` controls reset-token lifetime in seconds.

## Amazon development
Amazon is optional. Leave `AMAZON_API_ENABLED=false` to use the full manual CMS without credentials.
