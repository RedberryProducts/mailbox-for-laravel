<?php

namespace Redberry\MailboxForLaravel;

use Illuminate\Mail\MailManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Redberry\MailboxForLaravel\Commands\DevLinkCommand;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;
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
    }

    public function registeringPackage(): void
    {
        $this->registerStorage();
        $this->registerAttachmentStore();
        $this->registerCaptureService();
        $this->registerTransport();
        $this->registerDevCommands();
    }

    public function packageBooted(): void
    {
        $this->configureMailboxConnection();
        $this->configureMailboxDisk();
        $this->registerMiddleware();
        $this->registerGate();
        $this->registerPublishing();
    }

    /**
     * Bind the MessageStore implementation (via StoreManager).
     */
    protected function registerStorage(): void
    {
        $this->app->bind(StoreManager::class, function ($app) {
            return new StoreManager($app);
        });

        $this->app->bind(MessageStore::class, function ($app) {
            /** @var StoreManager $manager */
            $manager = $app->make(StoreManager::class);

            return $manager->driver();
        });
    }

    /**
     * Bind the AttachmentStore for attachment operations.
     */
    protected function registerAttachmentStore(): void
    {
        $this->app->singleton(AttachmentStore::class, function ($app) {
            return new AttachmentStore;
        });
    }

    /**
     * Bind the CaptureService, which depends on MessageStore.
     */
    protected function registerCaptureService(): void
    {
        $this->app->bind(CaptureService::class, function ($app) {
            return new CaptureService($app->make(MessageStore::class));
        });
    }

    /**
     * Register the custom "mailbox" mail transport, but only when enabled.
     *
     * Condition is intentionally preserved:
     *   - enabled on all non-production envs
     *   - OR when mailbox.enabled is explicitly true
     */
    protected function registerTransport(): void
    {
        if (config('app.env') === 'production' && ! config('mailbox.enabled', false)) {
            return;
        }

        $this->app->bind(MailboxTransport::class, function ($app) {
            return new MailboxTransport(
                $app->make(CaptureService::class),
                $app->make(AttachmentStore::class)
            );
        });

        $this->app->afterResolving(MailManager::class, function (MailManager $manager, $app): void {
            $manager->extend('mailbox', function () use ($app) {
                return $app->make(MailboxTransport::class);
            });
        });
    }

    /**
     * Register additional dev-only commands (e.g. DevLinkCommand) in local env.
     */
    protected function registerDevCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if ($this->app->environment('local')) {
            $this->commands([
                DevLinkCommand::class,
            ]);
        }
    }

    /**
     * Configure the dedicated "mailbox" SQLite connection.
     *
     * This keeps the test inbox DB completely isolated.
     */
    protected function configureMailboxConnection(): void
    {
        config([
            'database.connections.mailbox' => [
                'driver' => 'sqlite',
                'database' => storage_path('app/mailbox/mailbox.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
    }

    /**
     * Configure the mailbox filesystem disk for attachment storage.
     */
    protected function configureMailboxDisk(): void
    {
        config([
            'filesystems.disks.mailbox' => [
                'driver' => 'local',
                'root' => storage_path('app/mailbox'),
                'throw' => false,
            ],
        ]);
    }

    /**
     * Register middleware aliases for the mailbox dashboard.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('mailbox.authorize', AuthorizeMailboxMiddleware::class);
        $router->aliasMiddleware('mailbox.inertia', Http\Middleware\HandleInertiaRequests::class);
    }

    /**
     * Gate that controls access to the mailbox dashboard.
     *
     * Default behavior: allow everything except production.
     */
    protected function registerGate(): void
    {
        Gate::define('viewMailbox', static function ($user = null): bool {
            return ! app()->environment('production');
        });
    }

    /**
     * Asset/config/views publishing for the host application.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

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
