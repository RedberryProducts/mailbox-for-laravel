<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\SeenController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;

describe(SeenController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeMailboxMiddleware::class)->group(function () {
            Route::post('/mailbox/messages/{id}/seen', SeenController::class)->name('mailbox.messages.seen');
        });
        config()->set('mailbox.public', true);

        // Clear any existing messages before each test
        $service = app(CaptureService::class);
        $service->clearAll();
    });

    it('marks unseen message as seen and returns JSON response', function () {
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
        expect($message->seen_at)->toBeNull();

        // Mark as seen
        $response = $this->post("/mailbox/messages/{$key}/seen");

        $response->assertOk();
        $response->assertJson([
            'id' => $key,
        ]);

        $json = $response->json();
        expect($json['seen_at'])->not->toBeNull();
        expect($json['seen_at'])->toBeString();

        // Verify message is now marked as seen
        $updatedMessage = $service->retrieve($key);
        expect($updatedMessage->seen_at)->not->toBeNull();
        expect($updatedMessage->seen_at)->toBeString();

        // Parse the date to verify it's a valid timestamp
        $seenDate = Carbon\Carbon::parse($updatedMessage->seen_at);
        expect($seenDate)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('updates seen_at field with current timestamp and returns JSON', function () {
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
        $response->assertOk();

        $afterTimestamp = now();

        // Verify JSON response
        $json = $response->json();
        expect($json['id'])->toBe($key);
        expect($json['seen_at'])->toBeString();

        $seenDate = Carbon\Carbon::parse($json['seen_at']);
        expect($seenDate)->toBeInstanceOf(Carbon\Carbon::class);
        expect($seenDate->gte($beforeTimestamp))->toBeTrue();
        expect($seenDate->lte($afterTimestamp))->toBeTrue();

        // Verify timestamp is within expected range
        $updatedMessage = $service->retrieve($key);
        $seenAt = $updatedMessage->seen_at;

        expect($seenAt)->toBeString();
        $seenDate = Carbon\Carbon::parse($seenAt);
        expect($seenDate)->toBeInstanceOf(Carbon\Carbon::class);
        expect($seenDate->gte($beforeTimestamp))->toBeTrue();
        expect($seenDate->lte($afterTimestamp))->toBeTrue();
    });

    it('returns 404 for non-existent message', function () {
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

        // Should return 404 for non-existent message
        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Message not found.',
        ]);
    });

    it('throws error for invalid message key format', function () {
        // Test with invalid key format
        $invalidKey = 'invalid-key-format';

        // The CaptureService will throw an InvalidArgumentException for invalid keys
        $response = $this->post('/mailbox/messages/{invalidKey}/seen');
        $response->assertStatus(500);
    });

    it('does not overwrite existing seen_at timestamp (idempotent)', function () {
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
        $response1->assertOk();

        $firstJson = $response1->json();
        $firstSeenAt = $firstJson['seen_at'];

        // Wait a moment to ensure different timestamp would be generated
        sleep(1);

        // Mark as seen second time
        $response2 = $this->post("/mailbox/messages/{$key}/seen");
        $response2->assertOk();

        $secondJson = $response2->json();
        $secondSeenAt = $secondJson['seen_at'];

        // Verify timestamp was NOT updated (idempotent)
        expect($secondSeenAt)->toBe($firstSeenAt);

        // Also verify in storage
        $message = $service->retrieve($key);
        expect($message->seen_at)->toBe($firstSeenAt);
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
        $response->assertOk();
        $response->assertJson([
            'id' => $validKey,
        ]);
    });

    it('returns proper HTTP status code 200 OK with JSON', function () {
        $service = app(CaptureService::class);

        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $key = $service->store($payload);

        $response = $this->post("/mailbox/messages/{$key}/seen");

        expect($response->status())->toBe(200);
        $response->assertJson([
            'id' => $key,
        ]);

        $json = $response->json();
        expect($json['seen_at'])->toBeString();
    });
});
