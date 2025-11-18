<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\SendTestMailController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;

describe(SendTestMailController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeMailboxMiddleware::class)->group(function () {
            Route::post('/mailbox/test-email', SendTestMailController::class)->name('mailbox.test-email');
        });
        config()->set('mailbox.public', true);
    });

    it('sends sample mail through mailbox transport and returns stored key', function () {
        $response = $this->post('/mailbox/test-email');

        $response->assertOk()
            ->assertJson([
                'status' => 'stored',
            ])
            ->assertJsonStructure([
                'status',
                'key',
            ]);

        $key = $response->json('key');
        expect($key)->toBeString();
        expect($key)->toMatch('/^email_\d+_[a-f0-9]+$/');
    });

    it('stores a properly formatted test message with all required fields', function () {
        $service = app(CaptureService::class);

        $response = $this->post('/mailbox/test-email');
        $response->assertOk();

        $key = $response->json('key');
        $storedMessage = $service->find($key);

        expect($storedMessage->version)->toBe(1);
        expect($storedMessage->subject)->toBe('Test Mailbox for Laravel');
        expect($storedMessage->from)->toBeArray();
        expect($storedMessage->from[0])->toEqual([
            'email' => 'hello@example.com',
            'name' => 'Laravel',
        ]);
        expect($storedMessage->sender)->toEqual([
            'name' => 'Laravel',
            'email' => 'hello@example.com',
        ]);
        expect($storedMessage->to[0])->toEqual([
            'email' => 'recipient@example.com',
        ]);
        expect($storedMessage->html)->toContain('<h1>Hello from Mailbox for Laravel</h1>');
        expect($storedMessage->raw)->toContain('From: Laravel <hello@example.com>');
        expect($storedMessage->saved_at)->not->toBeNull();
        expect($storedMessage->headers)->toHaveKey('MIME-Version', '1.0');
        expect($storedMessage->headers)->toHaveKey('Content-Type', 'text/html; charset=utf-8');
    });

    it('creates test message with RFC3339 timestamp format', function () {
        $service = app(CaptureService::class);

        $response = $this->post('/mailbox/test-email');
        $response->assertOk();

        $key = $response->json('key');
        $storedMessage = $service->find($key);

        expect($storedMessage->saved_at)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{6})?([+-]\d{2}:\d{2}|Z)$/');
    });

    it('includes proper MIME headers and raw message format', function () {
        $service = app(CaptureService::class);

        $response = $this->post('/mailbox/test-email');
        $response->assertOk();

        $key = $response->json('key');
        $storedMessage = $service->find($key);

        $rawMessage = $storedMessage->raw;

        expect($rawMessage)->toContain('From: Laravel <hello@example.com>');
        expect($rawMessage)->toContain('To: recipient@example.com');
        expect($rawMessage)->toContain('Subject: Test Mailbox for Laravel');
        expect($rawMessage)->toContain('MIME-Version: 1.0');
        expect($rawMessage)->toContain('Content-Type: text/html; charset=utf-8');
        expect($rawMessage)->toContain('Content-Transfer-Encoding: quoted-printable');
        expect($rawMessage)->toContain('<h1>Hello from Mailbox for Laravel</h1>');
    });

    it('stores message with empty arrays for optional fields', function () {
        $service = app(CaptureService::class);

        $response = $this->post('/mailbox/test-email');
        $response->assertOk();

        $key = $response->json('key');
        $storedMessage = $service->find($key);

        expect($storedMessage->cc)->toBeArray()->toBeEmpty();
        expect($storedMessage->bcc)->toBeArray()->toBeEmpty();
        expect($storedMessage->reply_to)->toBeArray()->toBeEmpty();
        expect($storedMessage->attachments)->toBeArray()->toBeEmpty();
        // text can be null or empty string depending on message content
        expect($storedMessage->text ?? '')->toBeString();
        expect($storedMessage->date)->toBeNull();
        expect($storedMessage->message_id)->toBeNull();
    });
});
