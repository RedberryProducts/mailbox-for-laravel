<?php

namespace Redberry\MailboxForLaravel;

use App\Mail\Transport\CustomTransportFactory;
use Illuminate\Mail\MailManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Redberry\MailboxForLaravel\Commands\MailboxForLaravelCommand;
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
                return (new MailTransportFactory())->create(
                    new Dsn('mailbox-for-laravel', 'default')
                );
            });
        });
    }
}
