<?php

namespace Redberry\MailboxForLaravel;

use Illuminate\Mail\MailManager;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Transport\InboxTransport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mailbox-for-laravel')
            ->hasConfigFile('inbox');
        // keep room for: ->hasViews(), ->hasRoute() later for dashboard
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(MessageStore::class, function () {
            return (new StoreManager())->create();
        });
        $this->app->singleton(CaptureService::class, function () {
            return new CaptureService(app(MessageStore::class));
        });

        $this->app->singleton(InboxTransport::class, fn() => new InboxTransport(app(CaptureService::class)));

        // Register the mail driver
        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('inbox', function ($config) {
                // Return a Symfony TransportInterface
                return app(InboxTransport::class);
            });
        });
    }
}
