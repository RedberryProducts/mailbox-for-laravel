<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\ClearInboxController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeInboxMiddleware;

describe(ClearInboxController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeInboxMiddleware::class)->group(function () {
            Route::post('/mailbox/clear', ClearInboxController::class)->name('inbox.clear');
        });
        config()->set('inbox.public', true);
        
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
        
        // Verify messages exist
        $messagesBefore = $service->all();
        expect($messagesBefore)->toHaveCount(2);
        
        // Clear the inbox
        $response = $this->post('/mailbox/clear');
        
        $response->assertOk()
            ->assertJson([]);
        
        // Verify messages are cleared
        $messagesAfter = $service->all();
        expect($messagesAfter)->toBeEmpty();
    });

    it('returns json response even when inbox is already empty', function () {
        $service = app(CaptureService::class);
        
        // Ensure inbox is empty
        $messagesBefore = $service->all();
        expect($messagesBefore)->toBeEmpty();
        
        $response = $this->post('/mailbox/clear');
        
        $response->assertOk()
            ->assertJson([]);
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
        $response = $this->post('/mailbox/clear');
        
        $response->assertOk();
        
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
        
        // Verify message exists
        expect($service->retrieve($key))->not->toBeNull();
        
        $response = $this->post('/mailbox/clear');
        $response->assertOk();
        
        // Verify clearAll was effective
        expect($service->retrieve($key))->toBeNull();
        expect($service->all())->toBeEmpty();
    });

    it('returns proper content-type header for json response', function () {
        $response = $this->post('/mailbox/clear');
        
        $response->assertOk()
            ->assertHeader('content-type', 'application/json');
    });
});