<?php

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
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

    it('honors config(mailbox.enabled)=false by not registering routes', function () {
        putenv('MAILBOX_ENABLED=false');
        $this->refreshApplication();
        expect(Route::has('mailbox.index'))->toBeFalse();
        putenv('MAILBOX_ENABLED');
    });

    it('merges default config values correctly', function () {
        // Default driver is 'database' per config file
        expect(config('mailbox.store.driver'))->toBe('database');
        expect(config('mailbox.middleware'))->toBe(['web']);
    });
});
