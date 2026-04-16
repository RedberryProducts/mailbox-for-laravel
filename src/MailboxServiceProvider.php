<?php

namespace Redberry\MailboxForLaravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Redberry\MailboxForLaravel\Commands\DevLinkCommand;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore as AttachmentStoreContract;
use Redberry\MailboxForLaravel\Contracts\MessageSearch;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;
use Redberry\MailboxForLaravel\Search\DefaultMessageSearch;
use Redberry\MailboxForLaravel\Storage\DatabaseAttachmentStore;
use Redberry\MailboxForLaravel\Storage\FileAttachmentStore;
use Redberry\MailboxForLaravel\Support\CidRewriter;
use Redberry\MailboxForLaravel\Transport\MailboxTransport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\Mailer\Transport\TransportInterface;

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
                Commands\ClearInboxCommand::class,
                Commands\UpgradeCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->registerSearch();
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
        $this->registerRetentionSchedule();
    }

    /**
     * Register the daily retention purge on Laravel's scheduler.
     *
     * Delegates to scheduleRetentionPurge() so the guards are re-evaluated
     * at schedule-resolution time (rather than at boot), which keeps the
     * behavior testable with a fresh Schedule instance.
     */
    protected function registerRetentionSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->scheduleRetentionPurge($schedule);
        });
    }

    /**
     * Register the daily "mailbox:clear --outdated" command on the given schedule,
     * guarded by `mailbox.enabled`, `mailbox.retention > 0`, and
     * `mailbox.retention_schedule`.
     */
    public function scheduleRetentionPurge(Schedule $schedule): void
    {
        if (! config('mailbox.enabled')) {
            return;
        }

        if ((int) config('mailbox.retention', 0) <= 0) {
            return;
        }

        if (! config('mailbox.retention_schedule', true)) {
            return;
        }

        $schedule->command('mailbox:clear --outdated')
            ->daily()
            ->name('mailbox:retention-purge')
            ->onOneServer();
    }

    /**
     * Bind the MessageSearch strategy.
     *
     * Users can swap this binding to customize which fields are searched
     * and how matching works, without touching storage drivers.
     */
    protected function registerSearch(): void
    {
        $this->app->singleton(MessageSearch::class, DefaultMessageSearch::class);
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
     * Bind the AttachmentStore contract, paired with the active MessageStore driver.
     *
     * - `sqlite` (default) / `database` → DatabaseAttachmentStore (Eloquent metadata + disk content)
     * - `file`                          → FileAttachmentStore (per-message JSON sidecar + disk content)
     * - custom drivers may bind their own implementation in their resolver.
     */
    protected function registerAttachmentStore(): void
    {
        $this->app->singleton(AttachmentStoreContract::class, function ($app) {
            $driver = (string) config('mailbox.store.driver', 'sqlite');

            if ($driver === 'file') {
                $fileBase = (string) config('mailbox.store.file.path', storage_path('app/mailbox'));

                return new FileAttachmentStore(
                    rtrim($fileBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'attachments-index',
                    (string) config('mailbox.attachments.disk', 'mailbox'),
                    (string) config('mailbox.attachments.path', 'attachments'),
                );
            }

            return $app->make(DatabaseAttachmentStore::class);
        });

        $this->app->singleton(CidRewriter::class, function ($app) {
            return new CidRewriter($app->make(AttachmentStoreContract::class));
        });
    }

    /**
     * Bind the CaptureService, which depends on MessageStore + AttachmentStore.
     */
    protected function registerCaptureService(): void
    {
        $this->app->bind(CaptureService::class, function ($app) {
            return new CaptureService(
                $app->make(MessageStore::class),
                $app->make(AttachmentStoreContract::class),
            );
        });
    }

    /**
     * Register the custom "mailbox" mail transport, but only when enabled.
     * The "mailbox.enabled" config already encodes the env-based default.
     */
    protected function registerTransport(): void
    {
        if (! config('mailbox.enabled')) {
            return;
        }

        $this->app->bind(MailboxTransport::class, function ($app) {
            return new MailboxTransport(
                $app->make(CaptureService::class),
                $app->make(AttachmentStoreContract::class),
                $this->resolveDecoratedTransport($app),
            );
        });

        $this->app->afterResolving(MailManager::class, function (MailManager $manager, $app): void {
            $manager->extend('mailbox', function () use ($app) {
                return $app->make(MailboxTransport::class);
            });
        });
    }

    /**
     * Resolve the Symfony transport for the mailer named in "mailbox.decorate".
     *
     * Returns null when decoration is not configured, preserving capture-only
     * mode. Throws on circular references (decorating "mailbox" itself).
     *
     * @param  Application  $app
     */
    protected function resolveDecoratedTransport($app): ?TransportInterface
    {
        $name = config('mailbox.decorate');

        if ($name === null) {
            return null;
        }

        if ($name === 'mailbox') {
            throw new \InvalidArgumentException(
                'The [mailbox.decorate] option must not reference the "mailbox" mailer (circular reference).'
            );
        }

        /** @var MailManager $manager */
        $manager = $app->make(MailManager::class);

        return $manager->mailer($name)->getSymfonyTransport();
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
            'mail.mailers.mailbox' => [
                'transport' => 'mailbox',
            ],
        ]);

        $connectionName = config('mailbox.store.database.connection', 'mailbox');

        if (config("database.connections.{$connectionName}") === null) {
            config([
                "database.connections.{$connectionName}" => [
                    'driver' => 'sqlite',
                    'database' => storage_path('app/mailbox/mailbox.sqlite'),
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ]);
        }
    }

    /**
     * Configure the mailbox filesystem disk for attachment storage.
     */
    protected function configureMailboxDisk(): void
    {
        $diskName = config('mailbox.attachments.disk', 'mailbox');

        if (config("filesystems.disks.{$diskName}") === null) {
            config([
                "filesystems.disks.{$diskName}" => [
                    'driver' => 'local',
                    'root' => storage_path('app/mailbox'),
                    'throw' => false,
                ],
            ]);
        }
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
        $ability = config('mailbox.gate', 'viewMailbox');

        if (Gate::has($ability)) {
            return;
        }

        Gate::define($ability, static function ($user = null): bool {
            return app()->isLocal() || config('mailbox.enabled', false);
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
        ], 'mailbox-config');

        $this->publishes([
            __DIR__.'/../config/mailbox.php' => config_path('mailbox.php'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/mailbox'),
            __DIR__.'/../public/vendor/mailbox' => public_path('vendor/mailbox'),
        ], 'mailbox-install');
    }
}
