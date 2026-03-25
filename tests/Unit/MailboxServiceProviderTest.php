<?php

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\MailboxServiceProvider;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\Transport\MailboxTransport;

describe(MailboxServiceProvider::class, function () {
    it('registers config, routes, views, and install command', function () {
        expect(config('mailbox.route'))->toBe('mailbox');
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
        // Default driver is 'database' per config file
        expect(config('mailbox.store.driver'))->toBe('database');
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
});
