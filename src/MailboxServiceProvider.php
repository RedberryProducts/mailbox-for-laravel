<?php

namespace Redberry\MailboxForLaravel;

use Illuminate\Mail\MailManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Redberry\MailboxForLaravel\Commands\DevLinkCommand;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;
use Redberry\MailboxForLaravel\Transport\MailboxTransport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MailboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mailbox-for-laravel')
            ->hasConfigFile('mailbox')
            ->hasRoutes('mailbox')
            ->hasViews('mailbox')
            ->hasCommands([
                Commands\InstallCommand::class,
            ]);

        if ($this->app->environment('local')) {
            $this->commands([
                DevLinkCommand::class,
            ]);
        }

    }

    public function registeringPackage(): void
    {
        $this->app->singleton(MessageStore::class, fn () => (new StoreManager)->create());
        $this->app->singleton(CaptureService::class, fn () => new CaptureService(app(MessageStore::class)));

        if (config('app.env') !== 'production' || config('mailbox.enabled', false)) {
            $this->app->singleton(MailboxTransport::class, fn () => new MailboxTransport(app(CaptureService::class)));

            $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
                $manager->extend('mailbox', fn ($config) => app(MailboxTransport::class));
            });
        }

    }

    public function packageBooted(): void
    {
        $this->app->make(Router::class)
            ->aliasMiddleware('mailbox.authorize', AuthorizeMailboxMiddleware::class)
            ->aliasMiddleware('mailbox.inertia', Http\Middleware\HandleInertiaRequests::class);

        Gate::define('viewMailbox', function ($user = null) {
            // This closure only runs when Gate::allows() is called, i.e. during a request
            return ! app()->environment('production');
        });

        $this->publishes([
            __DIR__.'/../public/vendor/mailbox' => public_path('vendor/mailbox'),
        ], 'mailbox-assets');

        $this->publishes([
            __DIR__.'/../config/mailbox.php' => config_path('mailbox.php'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/mailbox'),
            __DIR__.'/../public/vendor/mailbox' => public_path('vendor/mailbox'),
        ], 'mailbox-install');

    }
}
