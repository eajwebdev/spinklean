---
name: verify
description: How to launch and drive the skl-ms Laravel app for runtime verification.
---

# Verifying skl-ms changes

Laravel 11 + Blade + Alpine, MySQL (`spinklean` on 127.0.0.1, XAMPP). `.env` says `APP_ENV=production` but this is a local dev copy.

## Launch

```bash
php artisan serve --host=127.0.0.1 --port=8787   # run in background
```

## Login (session + CSRF over curl)

Seeded users (see `database/seeders/UserSeeder.php`): login field is `login` (username or email), password `admin123`. Working credentials: `superadmin` / `admin123` (the seeded email was changed in this DB, so use the username).

```bash
curl -s -c jar.txt http://127.0.0.1:8787/login -o login.html
TOKEN=$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' login.html | head -1)
curl -s -b jar.txt -c jar.txt -X POST http://127.0.0.1:8787/login \
  -d "_token=$TOKEN" -d "login=superadmin" -d "password=admin123"
```

Forms use `_method=PATCH/DELETE` spoofing via POST. Grab a fresh `_token` from any authenticated page.

## Useful surfaces

- Cycle monitoring: `GET /admin/cycles?branch_id=1`; status change `POST /admin/cycles/job-orders/{id}/status` (`_method=PATCH`, `status=...`)
- Job orders: `POST /admin/job-orders/{id}/status`, `/cancel`, `/release` (all `_method=PATCH`)
- Flash messages render via `resources/views/partials/alerts.blade.php` (only `session('success'|'error')` — `withErrors` alone is invisible on non-form pages)

## Test data

Create/remove throwaway orders via tinker with `job_order_number` prefixed `VERIFY-TEST-`; clean up with `forceDelete()` (model is soft-deleting). Branch 1 has `machine_count=5`. SMS is gated by `SystemSetting::sms_enabled` — check `sms_logs` count stays 0 after driving status routes.
