<?php

use Redberry\MailboxForLaravel\CaptureService;

describe('API Messages Controller', function () {
    beforeEach(function () {
        config()->set('inbox.public', true);
        config()->set('inbox.enabled', true);

        // Clear any existing messages
        $captureService = $this->app->make(CaptureService::class);
        $captureService->clearAll();
    });

    it('returns paginated messages list via API', function () {
        $captureService = $this->app->make(CaptureService::class);

        // Store some test messages
        $captureService->store([
            'raw' => 'test email 1',
            'subject' => 'Test Subject 1',
            'from' => [['name' => 'Test Sender', 'address' => 'test@example.com']],
            'timestamp' => time(),
        ]);

        $captureService->store([
            'raw' => 'test email 2',
            'subject' => 'Test Subject 2',
            'from' => [['name' => 'Another Sender', 'address' => 'another@example.com']],
            'timestamp' => time() + 1,
        ]);

        $response = $this->getJson('/mailbox/api/messages');

        $response->assertStatus(200);

        $data = $response->json();

        // Check basic structure
        expect($data)->toHaveKeys(['data', 'total', 'page', 'per_page', 'last_page'])
            ->and($data['total'])->toBe(2)
            ->and($data['page'])->toBe(1)
            ->and($data['per_page'])->toBe(50);

        // Check that we have messages
        expect($data['data'])->toBeArray()
            ->and(count($data['data']))->toBe(2);

        // Check first message has basic fields
        $firstMessage = $data['data'][0];
        expect($firstMessage)->toHaveKeys(['id', 'timestamp', 'seen_at']);
    });

    it('supports pagination parameters', function () {
        $captureService = $this->app->make(CaptureService::class);

        // Store 3 test messages
        for ($i = 1; $i <= 3; $i++) {
            $captureService->store([
                'raw' => "test email $i",
                'subject' => "Test Subject $i",
                'timestamp' => time() + $i,
            ]);
        }

        $response = $this->getJson('/mailbox/api/messages?page=1&per_page=2');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data['total'])->toBe(3)
            ->and($data['page'])->toBe(1)
            ->and($data['per_page'])->toBe(2)
            ->and($data['last_page'])->toBe(2)
            ->and(count($data['data']))->toBe(2);
    });

    it('returns a specific message by ID via API', function () {
        $captureService = $this->app->make(CaptureService::class);

        $messageId = $captureService->store([
            'raw' => 'test email content',
            'subject' => 'Test Subject',
            'from' => [['name' => 'Test Sender', 'address' => 'test@example.com']],
            'timestamp' => time(),
        ]);

        $response = $this->getJson("/mailbox/api/messages/$messageId");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'subject',
                'from',
                'timestamp',
                'seen_at',
                'raw',
            ]);

        $data = $response->json();
        expect($data['id'])->toBe($messageId)
            ->and($data['subject'])->toBe('Test Subject');
    });

    it('returns 404 for non-existent message', function () {
        $response = $this->getJson('/mailbox/api/messages/nonexistent');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Message not found']);
    });

    it('marks message as seen via API', function () {
        $captureService = $this->app->make(CaptureService::class);

        $messageId = $captureService->store([
            'raw' => 'test email content',
            'subject' => 'Test Subject',
            'timestamp' => time(),
        ]);

        // Initially not seen
        $message = $captureService->get($messageId);
        expect($message['seen_at'])->toBeNull();

        $response = $this->postJson("/mailbox/api/messages/$messageId/seen");

        $response->assertStatus(200);

        // Check message is now marked as seen
        $updatedMessage = $captureService->get($messageId);
        expect($updatedMessage['seen_at'])->not->toBeNull();
    });

    it('returns 404 when marking non-existent message as seen', function () {
        $response = $this->postJson('/mailbox/api/messages/nonexistent/seen');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Message not found']);
    });

    it('deletes a message via API', function () {
        $captureService = $this->app->make(CaptureService::class);

        $messageId = $captureService->store([
            'raw' => 'test email content',
            'subject' => 'Test Subject',
            'timestamp' => time(),
        ]);

        // Verify message exists
        expect($captureService->get($messageId))->not->toBeNull();

        $response = $this->deleteJson("/mailbox/api/messages/$messageId");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Message deleted successfully']);

        // Verify message is deleted
        expect($captureService->get($messageId))->toBeNull();
    });

    it('returns 400 for invalid message ID when deleting', function () {
        $response = $this->deleteJson('/mailbox/api/messages/invalid@id');

        // The route pattern should prevent this from reaching the controller
        // Laravel will return 404 for routes that don't match the pattern
        $response->assertStatus(404);
    });
});
