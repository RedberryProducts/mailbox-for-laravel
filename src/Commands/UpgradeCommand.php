<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;

final class UpgradeCommand extends Command
{
    protected $signature = 'mailbox:upgrade
        {--fresh : Skip prompts and run a full refresh}';

    protected $description = 'Upgrade Mailbox for Laravel from v1.x to v2.0';

    /**
     * Stale v1 config keys mapped to their v2 replacements.
     *
     * @var array<string, string>
     */
    private const CONFIG_MIGRATIONS = [
        'mailbox.route' => 'mailbox.path',
        'mailbox.retention.seconds' => 'mailbox.retention',
        'mailbox.pagination.per_page' => 'mailbox.per_page',
    ];

    /**
     * Stale v1 env variables mapped to their v2 replacements.
     *
     * @var array<string, string>
     */
    private const ENV_MIGRATIONS = [
        'MAILBOX_DASHBOARD_ROUTE' => 'MAILBOX_PATH',
        'MAILBOX_FILE_PATH' => 'MAILBOX_STORE_FILE_PATH',
        'MAILBOX_DB_CONNECTION' => 'MAILBOX_STORE_DATABASE_CONNECTION',
        'MAILBOX_DB_TABLE' => 'MAILBOX_STORE_DATABASE_TABLE',
        'MAILBOX_REDIRECT' => 'MAILBOX_UNAUTHORIZED_REDIRECT',
    ];

    public function handle(): int
    {
        $this->info('Mailbox for Laravel — v2.0 upgrade');
        $this->newLine();

        $staleKeys = $this->detectStaleConfig();
        $staleEnvs = $this->detectStaleEnvVars();

        if ($staleKeys !== []) {
            $this->warn('Stale config keys detected (re-publish your config):');
            foreach ($staleKeys as $old => $new) {
                $this->line("  {$old}  →  {$new}");
            }
            $this->newLine();
        }

        if ($staleEnvs !== []) {
            $this->warn('Stale .env variables detected (rename in your .env):');
            foreach ($staleEnvs as $old => $new) {
                $this->line("  {$old}  →  {$new}");
            }
            $this->newLine();
        }

        if ($staleKeys === [] && $staleEnvs === []) {
            $this->info('No stale config keys or env variables found.');
            $this->newLine();
        }

        $shouldRefresh = $this->option('fresh')
            || $this->confirm('Refresh database schema? This drops and recreates mailbox tables (captured mail will be lost).', true);

        if ($shouldRefresh) {
            $this->call('mailbox:install', ['--refresh' => true, '--force' => true]);
            $this->newLine();
            $this->info('Schema refreshed and assets re-published.');
        } else {
            $this->call('mailbox:install', ['--force' => true]);
            $this->newLine();
            $this->info('Assets re-published (schema not refreshed).');
        }

        $this->newLine();
        $this->info('Upgrade complete. See UPGRADE.md for details on any manual steps.');

        return self::SUCCESS;
    }

    /**
     * Detect v1 config keys that are still present in the merged config.
     *
     * @return array<string, string>
     */
    public function detectStaleConfig(): array
    {
        $stale = [];

        foreach (self::CONFIG_MIGRATIONS as $old => $new) {
            if (config($old) !== null) {
                $stale[$old] = $new;
            }
        }

        return $stale;
    }

    /**
     * Detect v1 env variables that are still set.
     *
     * @return array<string, string>
     */
    public function detectStaleEnvVars(): array
    {
        $stale = [];

        foreach (self::ENV_MIGRATIONS as $old => $new) {
            if (getenv($old) !== false) {
                $stale[$old] = $new;
            }
        }

        return $stale;
    }
}
