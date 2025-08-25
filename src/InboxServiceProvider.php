<?php

namespace Redberry\MailboxForLaravel;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Gate;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Transport\InboxTransport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mailbox-for-laravel')      // must match composer.json "name"
            ->hasConfigFile('inbox')           // config/inbox.php
            ->hasRoutes('inbox')               // routes/inbox.php
            ->hasViews('inbox')                // resources/views/*
            ->hasCommands([
                Commands\InstallCommand::class,
            ]);

        // NOTE: hasAssets() publishes resources/assets → public/vendor/mailbox-for-laravel.
        // Our Vite build outputs to /dist, so hasAssets() will NOT help.
        // You can keep it if you also have static files under resources/assets.
        // ->hasAssets();
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(MessageStore::class, fn () => (new StoreManager())->create());
        $this->app->singleton(CaptureService::class, fn () => new CaptureService(app(MessageStore::class)));
        $this->app->singleton(InboxTransport::class, fn () => new InboxTransport(app(CaptureService::class)));

        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('inbox', fn ($config) => app(InboxTransport::class));
        });

        Gate::define('viewInbox', fn ($user = null) =>
        app()->environment(['local', 'development', 'staging'])
        );
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/mailbox'),
        ], 'mailbox-assets');

        $this->publishes([
            __DIR__ . '/../config/inbox.php'   => config_path('inbox.php'),
            __DIR__ . '/../resources/views'    => resource_path('views/vendor/inbox'),
            __DIR__ . '/../dist'               => public_path('vendor/mailbox'),
        ], 'mailbox-install');
    }
}
