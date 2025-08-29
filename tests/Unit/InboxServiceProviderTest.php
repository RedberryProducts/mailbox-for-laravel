<?php

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\InboxServiceProvider;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\Transport\InboxTransport;

describe(InboxServiceProvider::class, function () {
    it('registers config, routes, views, and install command', function () {
        expect(config('inbox.route'))->toBe('mailbox');
        expect(Route::has('inbox.index'))->toBeTrue();
        expect(view()->exists('inbox::index'))->toBeTrue();
        expect(Artisan::all())->toHaveKey('mailbox:install');
    });

    it('binds MessageStore contract to StoreManager->create() result', function () {
        $store = app(MessageStore::class);
        expect($store)->toBeInstanceOf(FileStorage::class);
    });

    it('binds CaptureService as singleton with MessageStore dependency', function () {
        $service1 = app(CaptureService::class);
        $service2 = app(CaptureService::class);
        expect($service1)->toBe($service2);

        $ref = new ReflectionProperty(CaptureService::class, 'storage');
        $ref->setAccessible(true);
        expect($ref->getValue($service1))->toBe(app(MessageStore::class));
    });

    it('registers inbox mail transport on boot', function () {
        $mailer = app(MailManager::class)->mailer('inbox');
        expect($mailer->getSymfonyTransport())->toBeInstanceOf(InboxTransport::class);
    });

    it('applies configured middleware to inbox routes', function () {
        $route = Route::getRoutes()->getByName('inbox.index');
        $middlewares = $route->gatherMiddleware();
        expect($middlewares)->toContain('web', 'mailbox.authorize');
    });

    it('honors config(inbox.enabled)=false by not registering routes', function () {
        putenv('INBOX_ENABLED=false');
        $this->refreshApplication();
        expect(Route::has('inbox.index'))->toBeFalse();
        putenv('INBOX_ENABLED');
    });

    it('merges default config values correctly', function () {
        expect(config('inbox.store.driver'))->toBe('file');
        expect(config('inbox.middleware'))->toBe(['web']);
        expect(config('inbox.public'))->toBeFalse();
    });
});
