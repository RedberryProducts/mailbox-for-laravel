<?php

use Illuminate\Support\Facades\Gate;
use Redberry\MailboxForLaravel\CaptureService;

describe('API Inbox Controller', function () {
    beforeEach(function () {
        config()->set('inbox.public', true);
        config()->set('inbox.enabled', true);
        
        // Clear any existing messages
        $captureService = $this->app->make(CaptureService::class);
        $captureService->clearAll();
    });

    it('clears all messages via API', function () {
        $captureService = $this->app->make(CaptureService::class);
        
        // Store some test messages
        $captureService->store([
            'raw' => 'test email 1',
            'subject' => 'Test Subject 1',
            'timestamp' => time(),
        ]);
        
        $captureService->store([
            'raw' => 'test email 2',
            'subject' => 'Test Subject 2', 
            'timestamp' => time() + 1,
        ]);

        // Verify messages exist
        $messages = $captureService->all();
        expect(count($messages))->toBe(2);

        $response = $this->postJson('/mailbox/api/clear');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Inbox cleared successfully']);

        // Verify all messages are cleared
        $messagesAfter = $captureService->all();
        expect(count($messagesAfter))->toBe(0);
    });

    it('returns inbox statistics via API', function () {
        $captureService = $this->app->make(CaptureService::class);
        
        // Store some test messages - some read, some unread
        $messageId1 = $captureService->store([
            'raw' => 'test email 1',
            'subject' => 'Test Subject 1',
            'timestamp' => time(),
        ]);
        
        $messageId2 = $captureService->store([
            'raw' => 'test email 2',
            'subject' => 'Test Subject 2',
            'timestamp' => time() + 1,
        ]);

        $messageId3 = $captureService->store([
            'raw' => 'test email 3',
            'subject' => 'Test Subject 3',
            'timestamp' => time() + 2,
        ]);

        // Mark one as seen
        $captureService->update($messageId1, ['seen_at' => now()]);

        $response = $this->getJson('/mailbox/api/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'unread',
                'read'
            ]);

        $data = $response->json();
        expect($data['total'])->toBe(3)
            ->and($data['unread'])->toBe(2)
            ->and($data['read'])->toBe(1);
    });

    it('returns correct stats when no messages exist', function () {
        $response = $this->getJson('/mailbox/api/stats');

        $response->assertStatus(200);
        $data = $response->json();
        
        expect($data['total'])->toBe(0)
            ->and($data['unread'])->toBe(0)
            ->and($data['read'])->toBe(0);
    });
});