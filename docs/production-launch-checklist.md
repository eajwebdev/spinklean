# SKL Management System Production Launch

## Required environment

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` to the final HTTPS domain.
- Keep `APP_TIMEZONE=Asia/Manila`.
- Use `LOG_STACK=daily` and `LOG_LEVEL=warning`.
- Set `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and `SESSION_SAME_SITE=lax` after HTTPS is active.
- Replace the MySQL root account with a dedicated application database user and a strong password.
- Configure a real mail transport if email delivery is required.
- Configure the UniSMS API secret key and optional approved sender ID in System Settings.

## Deploy

```bash
composer install --no-dev --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan production:check
```

Uploaded files are written directly to `public/uploads`; no storage symlink is required.

The application currently has no scheduled commands or queued jobs. A scheduler or queue worker is not required until asynchronous jobs are introduced.

## Verify

```bash
php artisan migrate:status
php artisan about
php artisan production:check
php artisan test
php artisan queue:failed
```

- Do not launch while `production:check` reports any failed checks.
- Confirm `/up` returns HTTP 200 through the public HTTPS domain.
- Submit a test Job Order and confirm cash, GCash, bank, expenses, Accounts Payable, Dashboard, Reports, and Z Reading reconcile.
- Send one real UniSMS SMS to a non-PO test customer.
- Confirm PO customers do not create SMS attempts.
- Create a database backup and test restoring it to a separate database.

## Rollback

1. Put the application in maintenance mode with `php artisan down`.
2. Restore the last verified database backup.
3. Deploy the previous application release and its matching built assets.
4. Run `php artisan optimize:clear`, then `php artisan optimize`.
5. Run smoke tests before `php artisan up`.
