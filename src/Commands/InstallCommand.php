<?php

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'mailbox:install {--force} {--refresh} {--dev}';

    protected $description = 'Install Mailbox package (publish assets or link them in dev mode).';

    public function handle(): int
    {
        if ($this->option('dev')) {
            $this->info('Dev mode: linking assets (reusing mailbox:dev-link)...');

            $this->call('mailbox:dev-link');

        } else {
            $this->publishAssets();
            $this->info('Mailbox assets published.');
        }

        $this->runMigrations();
        $this->info('Mailbox migrations run.');

        return self::SUCCESS;
    }

    /**
     * Run package migrations.
     */
    public function runMigrations(): void
    {
        $command = $this->option('refresh') ? 'migrate:refresh' : 'migrate';
        $connectionName = config('mailbox.store.database.connection', 'mailbox');
        $connection = config("database.connections.{$connectionName}");

        if (! $connection) {
            $this->error("Database connection [{$connectionName}] is not configured.");

            return;
        }

        $dbPath = $connection['database'] ?? null;
        $driver = $connection['driver'] ?? null;

        if ($driver === 'sqlite' && $dbPath && $dbPath !== ':memory:') {
            $directory = dirname($dbPath);

            if (! File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            if ($this->option('refresh') && File::exists($dbPath)) {
                File::delete($dbPath);
            }

            if (! File::exists($dbPath)) {
                File::put($dbPath, '');
            }
        }

        $this->call($command, [
            '--path' => 'vendor/redberry/mailbox-for-laravel/database/migrations',
            '--database' => $connectionName,
            '--force' => true,
        ]);
    }

    /**
     * Regular publishing for production environments.
     */
    public function publishAssets(): void
    {
        $path = public_path('vendor/mailbox');

        if (file_exists($path) && is_link($path)) {
            @unlink($path);
        }

        if (File::exists($path)) {
            File::deleteDirectory($path);
        }

        $this->call('vendor:publish', [
            '--tag' => 'mailbox-assets',
            '--force' => (bool) $this->option('force'),
        ]);
    }
}
