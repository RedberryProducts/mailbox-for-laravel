<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\SeenController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeInboxMiddleware;

describe(SeenController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeInboxMiddleware::class)->group(function () {
            Route::post('/mailbox/messages/{id}/seen', SeenController::class)->name('inbox.messages.seen');
        });
        config()->set('inbox.public', true);

        // Clear any existing messages before each test
        $service = app(CaptureService::class);
        $service->clearAll();
    });

    it('toggles seen_at timestamp and returns no content response', function () {
        $service = app(CaptureService::class);

        // Store a message
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
            'seen_at' => null,
        ];

        $key = $service->store($payload);

        // Verify message is initially unseen
        $message = $service->retrieve($key);
        expect($message['seen_at'])->toBeNull();

        // Mark as seen
        $response = $this->post("/mailbox/messages/{$key}/seen");

        $response->assertNoContent();

        // Verify message is now marked as seen
        $updatedMessage = $service->retrieve($key);
        expect($updatedMessage['seen_at'])->not->toBeNull();
        expect($updatedMessage['seen_at'])->toBeString();

        // Parse the date to verify it's a valid timestamp
        $seenDate = Carbon\Carbon::parse($updatedMessage['seen_at']);
        expect($seenDate)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('updates seen_at field with current timestamp', function () {
        $service = app(CaptureService::class);

        // Store a message
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $key = $service->store($payload);

        $beforeTimestamp = now();

        $response = $this->post("/mailbox/messages/{$key}/seen");
        $response->assertNoContent();

        $afterTimestamp = now();

        // Verify timestamp is within expected range
        $updatedMessage = $service->retrieve($key);
        $seenAt = $updatedMessage['seen_at'];

        expect($seenAt)->toBeString();
        $seenDate = Carbon\Carbon::parse($seenAt);
        expect($seenDate)->toBeInstanceOf(Carbon\Carbon::class);
        expect($seenDate->gte($beforeTimestamp))->toBeTrue();
        expect($seenDate->lte($afterTimestamp))->toBeTrue();
    });

    it('handles non-existent message gracefully', function () {
        // First, create and then delete a message to get a valid format key
        $service = app(CaptureService::class);
        $payload = [
            'subject' => 'Temp Email',
            'from' => [['email' => 'temp@example.com']],
            'raw' => 'Temp email content',
        ];

        $tempKey = $service->store($payload);
        $service->delete($tempKey);

        // Now use this deleted key (valid format but nonexistent)
        $response = $this->post("/mailbox/messages/{$tempKey}/seen");

        // Should still return no content even if message doesn't exist
        // This follows the idempotent principle for REST APIs
        $response->assertNoContent();
    });

    it('throws error for invalid message key format', function () {
        // Test with invalid key format
        $invalidKey = 'invalid-key-format';

        // The CaptureService will throw an InvalidArgumentException for invalid keys
        $response = $this->post('/mailbox/messages/{invalidKey}/seen');
        $response->assertStatus(500);
    });

    it('allows marking already seen message as seen again', function () {
        $service = app(CaptureService::class);

        // Store a message and mark it as seen
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $key = $service->store($payload);

        // Mark as seen first time
        $response1 = $this->post("/mailbox/messages/{$key}/seen");
        $response1->assertNoContent();

        $firstSeenMessage = $service->retrieve($key);
        $firstSeenAt = $firstSeenMessage['seen_at'];

        // Wait a moment to ensure different timestamp
        sleep(1);

        // Mark as seen second time
        $response2 = $this->post("/mailbox/messages/{$key}/seen");
        $response2->assertNoContent();

        $secondSeenMessage = $service->retrieve($key);
        $secondSeenAt = $secondSeenMessage['seen_at'];

        // Verify timestamp was updated
        expect($secondSeenAt)->toBeString();
        $secondDate = Carbon\Carbon::parse($secondSeenAt);
        $firstDate = Carbon\Carbon::parse($firstSeenAt);
        expect($secondDate)->toBeInstanceOf(Carbon\Carbon::class);
        expect($secondDate->gt($firstDate))->toBeTrue();
    });

    it('validates message key format before processing', function () {
        $service = app(CaptureService::class);

        // Store a valid message first
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $validKey = $service->store($payload);

        // Test with valid key format
        $response = $this->post("/mailbox/messages/{$validKey}/seen");
        $response->assertNoContent();
    });

    it('returns proper HTTP status code 204 No Content', function () {
        $service = app(CaptureService::class);

        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $key = $service->store($payload);

        $response = $this->post("/mailbox/messages/{$key}/seen");

        expect($response->status())->toBe(204);
        expect($response->getContent())->toBe('');
    });
});
