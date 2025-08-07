<?php

namespace Redberry\MailboxForLaravel;

use Illuminate\Mail\MailManager;
use Redberry\MailboxForLaravel\Commands\MailboxForLaravelCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;

class MailboxForLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('mailbox-for-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_mailbox_for_laravel_table')
            ->hasCommand(MailboxForLaravelCommand::class);

        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('mailbox-for-laravel', function () {
                return (new MailTransportFactory)->create(
                    new Dsn('mailbox-for-laravel', 'default')
                );
            });
        });
    }
}
