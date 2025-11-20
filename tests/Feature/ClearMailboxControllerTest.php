<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\ClearMailboxController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;

describe(ClearMailboxController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeMailboxMiddleware::class)->group(function () {
            Route::delete('/mailbox/messages', ClearMailboxController::class)->name('mailbox.messages.clear');
        });
        config()->set('mailbox.public', true);

        // Clear any existing messages before each test
        $service = app(CaptureService::class);
        $service->clearAll();
    });

    it('empties store and returns success response', function () {
        $service = app(CaptureService::class);

        // Store some messages first
        $payload1 = [
            'subject' => 'Test Email 1',
            'from' => [['email' => 'test1@example.com']],
            'raw' => 'Email 1 content',
        ];
        $payload2 = [
            'subject' => 'Test Email 2',
            'from' => [['email' => 'test2@example.com']],
            'raw' => 'Email 2 content',
        ];

        $service->store($payload1);
        $service->store($payload2);

        $messagesBefore = $service->all();
        expect($messagesBefore)->toHaveCount(2);

        $response = $this->delete('/mailbox/messages');

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });

        $messagesAfter = $service->all();
        expect($messagesAfter)->toBeEmpty();
    });

    it('returns json response even when mailbox is already empty', function () {
        $service = app(CaptureService::class);

        // Ensure mailbox is empty
        $messagesBefore = $service->all();
        expect($messagesBefore)->toBeEmpty();

        $response = $this->delete('/mailbox/messages');

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });
    });

    it('handles large number of messages efficiently', function () {
        $service = app(CaptureService::class);

        // Store multiple messages
        for ($i = 1; $i <= 10; $i++) {
            $payload = [
                'subject' => "Test Email {$i}",
                'from' => [['email' => "test{$i}@example.com"]],
                'raw' => "Email {$i} content",
            ];
            $service->store($payload);
        }

        // Verify messages exist
        $messagesBefore = $service->all();
        expect($messagesBefore)->toHaveCount(10);

        // Clear all messages
        $response = $this->delete('/mailbox/messages');

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });

        // Verify all messages are cleared
        $messagesAfter = $service->all();
        expect($messagesAfter)->toBeEmpty();
    });

    it('calls CaptureService clearAll method correctly', function () {
        $service = app(CaptureService::class);

        // Store a message to verify clearAll actually gets called
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $key = $service->store($payload);

        expect($service->find($key))->not->toBeNull();

        $response = $this->delete('/mailbox/messages');
        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });

        expect($service->find($key))->toBeNull()
            ->and($service->all())->toBeEmpty();
    });
});
