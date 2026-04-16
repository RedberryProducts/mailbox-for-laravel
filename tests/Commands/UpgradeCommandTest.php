<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Redberry\MailboxForLaravel\Commands\UpgradeCommand;

describe(UpgradeCommand::class, function () {
    it('is registered as an artisan command', function () {
        expect(Artisan::all())->toHaveKey('mailbox:upgrade');
    });

    it('detects stale v1 config keys', function () {
        config(['mailbox.route' => '/old-mailbox']);
        config(['mailbox.retention.seconds' => 3600]);

        $command = new UpgradeCommand;
        $stale = $command->detectStaleConfig();

        expect($stale)->toHaveKey('mailbox.route', 'mailbox.path')
            ->and($stale)->toHaveKey('mailbox.retention.seconds', 'mailbox.retention');
    });

    it('returns empty when no stale config keys exist', function () {
        $command = new UpgradeCommand;
        $stale = $command->detectStaleConfig();

        expect($stale)->toBeEmpty();
    });

    it('detects stale v1 env variables', function () {
        putenv('MAILBOX_DASHBOARD_ROUTE=/old');

        $command = new UpgradeCommand;
        $stale = $command->detectStaleEnvVars();

        expect($stale)->toHaveKey('MAILBOX_DASHBOARD_ROUTE', 'MAILBOX_PATH');

        putenv('MAILBOX_DASHBOARD_ROUTE');
    });

    it('returns empty when no stale env variables exist', function () {
        $command = new UpgradeCommand;
        $stale = $command->detectStaleEnvVars();

        expect($stale)->toBeEmpty();
    });

    it('runs successfully with --fresh flag', function () {
        $this->artisan('mailbox:upgrade', ['--fresh' => true])
            ->expectsOutput('Mailbox for Laravel — v2.0 upgrade')
            ->expectsOutput('Upgrade complete. See UPGRADE.md for details on any manual steps.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('prompts for schema refresh when run interactively', function () {
        $this->artisan('mailbox:upgrade')
            ->expectsConfirmation('Refresh database schema? This drops and recreates mailbox tables (captured mail will be lost).', 'no')
            ->expectsOutput('Assets re-published (schema not refreshed).')
            ->assertExitCode(Command::SUCCESS);
    });
});
