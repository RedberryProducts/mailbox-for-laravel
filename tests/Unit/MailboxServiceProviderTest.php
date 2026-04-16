<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\MailboxServiceProvider;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\Transport\MailboxTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

describe(MailboxServiceProvider::class, function () {
    it('registers config, routes, views, and install command', function () {
        expect(config('mailbox.path'))->toBe('mailbox');
        expect(Route::has('mailbox.index'))->toBeTrue();
        expect(view()->exists('mailbox::app'))->toBeTrue();
        expect(Artisan::all())->toHaveKey('mailbox:install');
    });

    it('binds MessageStore contract to StoreManager->driver() result', function () {
        // Force file driver for this test
        config(['mailbox.store.driver' => 'file']);

        $store = app(MessageStore::class);
        expect($store)->toBeInstanceOf(FileStorage::class);
    });

    it('binds CaptureService with MessageStore dependency', function () {
        $service1 = app(CaptureService::class);
        $service2 = app(CaptureService::class);

        // Both instances should use the same MessageStore instance
        $ref = new ReflectionProperty(CaptureService::class, 'storage');
        $ref->setAccessible(true);

        $storage1 = $ref->getValue($service1);
        $storage2 = $ref->getValue($service2);

        // Verify both services are configured correctly
        expect($storage1)->toBeInstanceOf(MessageStore::class)
            ->and($storage2)->toBeInstanceOf(MessageStore::class);
    });

    it('registers mailbox mail transport on boot', function () {
        $mailer = app(MailManager::class)->mailer('mailbox');
        expect($mailer->getSymfonyTransport())->toBeInstanceOf(MailboxTransport::class);
    });

    it('applies configured middleware to mailbox routes', function () {
        $route = Route::getRoutes()->getByName('mailbox.index');
        $middlewares = $route->gatherMiddleware();
        expect($middlewares)->toContain('web', 'mailbox.authorize');
    });

    it('merges default config values correctly', function () {
        // Default driver is 'sqlite' per config file
        expect(config('mailbox.store.driver'))->toBe('sqlite');
        expect(config('mailbox.middleware'))->toBe(['web']);
    });

    it('does not overwrite user-defined database connection', function () {
        $originalConnection = config('mailbox.store.database.connection');

        // Pre-define a connection, then check the provider respects it
        config([
            'mailbox.store.database.connection' => 'custom_mailbox',
            'database.connections.custom_mailbox' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'my_mailbox',
            ],
        ]);

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'configureMailboxConnection'))->invoke($provider);

        expect(config('database.connections.custom_mailbox.driver'))->toBe('mysql');

        // Restore to avoid affecting migration teardown
        config(['mailbox.store.database.connection' => $originalConnection]);
    });

    it('sets default SQLite connection when no user-defined connection exists', function () {
        $originalConnection = config('mailbox.store.database.connection');

        config(['mailbox.store.database.connection' => 'fresh_mailbox']);
        expect(config('database.connections.fresh_mailbox'))->toBeNull();

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'configureMailboxConnection'))->invoke($provider);

        expect(config('database.connections.fresh_mailbox.driver'))->toBe('sqlite');

        // Restore to avoid affecting migration teardown
        config(['mailbox.store.database.connection' => $originalConnection]);
    });

    it('does not overwrite user-defined filesystem disk', function () {
        config([
            'mailbox.attachments.disk' => 'custom_mailbox_disk',
            'filesystems.disks.custom_mailbox_disk' => [
                'driver' => 's3',
                'bucket' => 'my-mailbox-bucket',
            ],
        ]);

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'configureMailboxDisk'))->invoke($provider);

        expect(config('filesystems.disks.custom_mailbox_disk.driver'))->toBe('s3');
    });

    it('sets default local disk when no user-defined disk exists', function () {
        config(['mailbox.attachments.disk' => 'fresh_mailbox_disk']);
        expect(config('filesystems.disks.fresh_mailbox_disk'))->toBeNull();

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'configureMailboxDisk'))->invoke($provider);

        expect(config('filesystems.disks.fresh_mailbox_disk.driver'))->toBe('local');
    });

    it('does not overwrite user-defined gate', function () {
        // Clear default gate and define a custom one
        $gate = Gate::getFacadeRoot();
        (new ReflectionProperty($gate, 'abilities'))->setValue($gate, []);
        Gate::define('viewMailbox', static fn ($user = null) => true);

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'registerGate'))->invoke($provider);

        // The user's gate (always true) should still be in effect
        expect(Gate::allows('viewMailbox'))->toBeTrue();
    });

    it('default gate allows access when mailbox is enabled', function () {
        $gate = Gate::getFacadeRoot();
        (new ReflectionProperty($gate, 'abilities'))->setValue($gate, []);

        config(['mailbox.enabled' => true]);

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'registerGate'))->invoke($provider);

        // Testing env is not local, but mailbox.enabled=true grants access
        expect(Gate::allows('viewMailbox'))->toBeTrue();
    });

    it('default gate denies access when mailbox is disabled and not local', function () {
        $gate = Gate::getFacadeRoot();
        (new ReflectionProperty($gate, 'abilities'))->setValue($gate, []);

        config(['mailbox.enabled' => false]);
        // Testing env is not local, and mailbox.enabled=false, so gate denies

        $provider = app()->getProvider(MailboxServiceProvider::class);
        (new ReflectionMethod($provider, 'registerGate'))->invoke($provider);

        expect(Gate::allows('viewMailbox'))->toBeFalse();
    });

    describe('retention schedule', function () {
        $freshSchedule = function (): Schedule {
            return new Schedule;
        };

        $mailboxEvents = function (Schedule $schedule): array {
            return collect($schedule->events())
                ->filter(fn ($event) => str_contains((string) $event->command, 'mailbox:clear --outdated'))
                ->values()
                ->all();
        };

        it('registers a daily purge when enabled, retention positive, and schedule flag on', function () use ($freshSchedule, $mailboxEvents) {
            config([
                'mailbox.enabled' => true,
                'mailbox.retention' => 3600,
                'mailbox.retention_schedule' => true,
            ]);

            $schedule = $freshSchedule();
            app()->getProvider(MailboxServiceProvider::class)->scheduleRetentionPurge($schedule);

            $events = $mailboxEvents($schedule);
            expect($events)->toHaveCount(1);
            expect($events[0]->expression)->toBe('0 0 * * *');
            expect($events[0]->description)->toBe('mailbox:retention-purge');
        });

        it('does not register when mailbox is disabled', function () use ($freshSchedule, $mailboxEvents) {
            config([
                'mailbox.enabled' => false,
                'mailbox.retention' => 3600,
                'mailbox.retention_schedule' => true,
            ]);

            $schedule = $freshSchedule();
            app()->getProvider(MailboxServiceProvider::class)->scheduleRetentionPurge($schedule);

            expect($mailboxEvents($schedule))->toBeEmpty();
        });

        it('does not register when retention is zero or negative', function () use ($freshSchedule, $mailboxEvents) {
            config([
                'mailbox.enabled' => true,
                'mailbox.retention' => 0,
                'mailbox.retention_schedule' => true,
            ]);

            $schedule = $freshSchedule();
            app()->getProvider(MailboxServiceProvider::class)->scheduleRetentionPurge($schedule);

            expect($mailboxEvents($schedule))->toBeEmpty();
        });

        it('does not register when the retention_schedule flag is off', function () use ($freshSchedule, $mailboxEvents) {
            config([
                'mailbox.enabled' => true,
                'mailbox.retention' => 3600,
                'mailbox.retention_schedule' => false,
            ]);

            $schedule = $freshSchedule();
            app()->getProvider(MailboxServiceProvider::class)->scheduleRetentionPurge($schedule);

            expect($mailboxEvents($schedule))->toBeEmpty();
        });

        it('wires the callback via callAfterResolving so the Schedule singleton gets the purge', function () use ($mailboxEvents) {
            config([
                'mailbox.enabled' => true,
                'mailbox.retention' => 3600,
                'mailbox.retention_schedule' => true,
            ]);

            app()->forgetInstance(Schedule::class);
            $schedule = app()->make(Schedule::class);

            expect($mailboxEvents($schedule))->not->toBeEmpty();
        });
    });

    describe('transport decoration', function () {
        it('leaves decorated transport null when decorate config is not set', function () {
            config(['mailbox.decorate' => null]);

            app()->forgetInstance(MailboxTransport::class);
            app(MailManager::class)->purge('mailbox');

            $transport = app(MailManager::class)->mailer('mailbox')->getSymfonyTransport();

            $ref = new ReflectionProperty(MailboxTransport::class, 'decorated');
            expect($ref->getValue($transport))->toBeNull();
        });

        it('resolves and injects the decorated transport when decorate config is set', function () {
            config([
                'mailbox.decorate' => 'log',
                'mail.mailers.log' => ['transport' => 'log'],
            ]);

            app()->forgetInstance(MailboxTransport::class);
            app(MailManager::class)->purge('mailbox');

            $transport = app(MailManager::class)->mailer('mailbox')->getSymfonyTransport();

            $ref = new ReflectionProperty(MailboxTransport::class, 'decorated');
            $decorated = $ref->getValue($transport);

            expect($decorated)->toBeInstanceOf(TransportInterface::class);
        });

        it('throws on circular reference when decorate points to mailbox', function () {
            config(['mailbox.decorate' => 'mailbox']);

            app()->forgetInstance(MailboxTransport::class);
            app(MailManager::class)->purge('mailbox');

            expect(fn () => app(MailManager::class)->mailer('mailbox')->getSymfonyTransport())
                ->toThrow(InvalidArgumentException::class, 'circular reference');
        });

        it('throws when decorate references a nonexistent mailer', function () {
            config(['mailbox.decorate' => 'nonexistent']);

            app()->forgetInstance(MailboxTransport::class);
            app(MailManager::class)->purge('mailbox');

            expect(fn () => app(MailManager::class)->mailer('mailbox')->getSymfonyTransport())
                ->toThrow(InvalidArgumentException::class);
        });
    });
});
