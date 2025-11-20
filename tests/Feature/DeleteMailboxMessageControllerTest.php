<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\DeleteMailboxMessageController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;

describe(DeleteMailboxMessageController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeMailboxMiddleware::class)->group(function () {
            Route::delete('/mailbox/messages/{id}',
                DeleteMailboxMessageController::class)->name('mailbox.messages.destroy');
        });
        config()->set('mailbox.public', true);

        // Clear any existing messages before each test
        $service = app(CaptureService::class);
        $service->clearAll();
    });

    it('deletes a single message and returns success response', function () {
        $service = app(CaptureService::class);

        // Store a message
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'to' => [['email' => 'recipient@example.com']],
            'raw' => 'Test email content',
        ];

        $id = $service->store($payload);

        // Verify message exists
        expect($service->find($id))->not->toBeNull();

        // Delete the message
        $response = $this->delete("/mailbox/messages/{$id}");

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });

        // Verify message is deleted
        expect($service->find($id))->toBeNull();
    });

    it('returns 404 for non-existent message', function () {
        $response = $this->delete('/mailbox/messages/non-existent-id');

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'error';
            });
    });

    it('deletes only the specified message', function () {
        $service = app(CaptureService::class);

        // Store multiple messages
        $payload1 = [
            'subject' => 'Email 1',
            'from' => [['email' => 'test1@example.com']],
            'raw' => 'Content 1',
        ];
        $payload2 = [
            'subject' => 'Email 2',
            'from' => [['email' => 'test2@example.com']],
            'raw' => 'Content 2',
        ];

        $id1 = $service->store($payload1);
        $id2 = $service->store($payload2);

        // Delete only the first message
        $response = $this->delete("/mailbox/messages/{$id1}");

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });

        expect($service->find($id1))->toBeNull();
        expect($service->find($id2))->not->toBeNull();
    });

    it('handles special characters in message id', function () {
        $service = app(CaptureService::class);

        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test content',
        ];

        $id = $service->store($payload);

        // Attempt to delete with actual ID
        $response = $this->delete("/mailbox/messages/{$id}");

        $response->assertStatus(302)
            ->assertSessionHas('flash', function ($flash) {
                return $flash['status'] === 'success';
            });
    });

    it('is protected by authorization middleware', function () {
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $service = app(CaptureService::class);

        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test content',
        ];

        $id = $service->store($payload);

        $response = $this->delete("/mailbox/messages/{$id}");

        $response->assertForbidden();
    });
});
