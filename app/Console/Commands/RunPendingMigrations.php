<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunPendingMigrations extends Command
{
    protected $signature = 'migrations:run-pending';
    protected $description = 'Run pending database migrations when new migration files are deployed';

    public function handle(Migrator $migrator): int
    {
        $lock = Cache::lock('scheduled-pending-migrations', 300);

        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $repository = app('migration.repository');
            $paths = [database_path('migrations'), ...$migrator->paths()];
            $migrations = $migrator->getMigrationFiles($paths);
            $hasPendingMigrations = ! $repository->repositoryExists()
                || array_diff(array_keys($migrations), $repository->getRan()) !== [];

            if (! $hasPendingMigrations) {
                return self::SUCCESS;
            }

            $this->info('Running pending database migrations.');

            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            if ($output !== '') {
                $this->line($output);
            }

            return $exitCode;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pending migrations could not be run.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}