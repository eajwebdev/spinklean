<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('production:check', function (Migrator $migrator) {
    $checks = [];

    $addCheck = function (string $check, bool $passed, string $details) use (&$checks): void {
        $checks[] = [
            $passed ? 'PASS' : 'FAIL',
            $check,
            $details,
        ];
    };

    $appUrl = (string) config('app.url');
    $appHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
    $isPublicHttpsUrl = str_starts_with($appUrl, 'https://')
        && $appHost !== ''
        && ! in_array($appHost, ['localhost', '127.0.0.1'], true);

    $addCheck('Environment', app()->environment('production'), 'APP_ENV must be production.');
    $addCheck('Debug mode', config('app.debug') === false, 'APP_DEBUG must be false.');
    $addCheck('Application key', filled(config('app.key')), 'APP_KEY must be configured.');
    $addCheck('Public URL', $isPublicHttpsUrl, 'APP_URL must use the final public HTTPS domain.');
    $addCheck('Session encryption', config('session.encrypt') === true, 'SESSION_ENCRYPT must be true.');
    $addCheck('Secure session cookie', config('session.secure') === true, 'SESSION_SECURE_COOKIE must be true.');

    try {
        DB::select('select 1');
        $addCheck('Database connection', true, 'Database connection succeeded.');
    } catch (Throwable $exception) {
        $addCheck('Database connection', false, 'Database connection failed.');
    }

    try {
        $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));
        $pendingMigrations = array_diff(array_keys($migrationFiles), $migrator->getRepository()->getRan());
        $addCheck(
            'Database migrations',
            $pendingMigrations === [],
            $pendingMigrations === []
                ? 'All migrations are applied.'
                : count($pendingMigrations).' migration(s) are pending.'
        );
    } catch (Throwable $exception) {
        $addCheck('Database migrations', false, 'Migration status could not be read.');
    }

    $uploadsPath = public_path('uploads');
    $addCheck(
        'Public uploads',
        is_dir($uploadsPath) && is_writable($uploadsPath),
        'public/uploads must exist and be writable.'
    );
    $addCheck('Storage writable', is_writable(storage_path()), 'The storage directory must be writable.');
    $addCheck('Cache writable', is_writable(base_path('bootstrap/cache')), 'bootstrap/cache must be writable.');
    $addCheck('Built assets', file_exists(public_path('build/manifest.json')), 'Run npm run build before launch.');

    try {
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $addCheck(
            'Failed queue jobs',
            $failedJobs === 0,
            $failedJobs === 0 ? 'No failed jobs.' : $failedJobs.' failed job(s) require review.'
        );
    } catch (Throwable $exception) {
        $addCheck('Failed queue jobs', false, 'Failed jobs could not be checked.');
    }

    $this->table(['Status', 'Check', 'Details'], $checks);

    $failedChecks = collect($checks)->where(0, 'FAIL')->count();

    if ($failedChecks > 0) {
        $this->error($failedChecks.' production readiness check(s) failed.');

        return Command::FAILURE;
    }

    $this->info('All production readiness checks passed.');

    return Command::SUCCESS;
})->purpose('Validate the application environment before a production launch');
