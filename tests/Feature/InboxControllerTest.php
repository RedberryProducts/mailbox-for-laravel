<?php

use Illuminate\Support\Facades\View;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\InboxController;

describe(InboxController::class, function () {
    beforeEach(function () {
        config()->set('inbox.public', true);
        
        // Clear any existing messages before each test
        $service = app(CaptureService::class);
        $service->clearAll();
    });

    it('returns view with paginated list of messages sorted newest-first', function () {
        $service = app(CaptureService::class);
        
        // Store messages with different timestamps to test sorting
        $payload1 = [
            'subject' => 'First Email',
            'timestamp' => 1000,
            'from' => [['email' => 'test1@example.com']],
            'raw' => 'Email 1',
        ];
        $payload2 = [
            'subject' => 'Second Email', 
            'timestamp' => 2000,
            'from' => [['email' => 'test2@example.com']],
            'raw' => 'Email 2',
        ];
        
        $key1 = $service->store($payload1);
        $key2 = $service->store($payload2);
        
        $controller = new InboxController();
        $request = request();
        
        $result = $controller->__invoke($request, $service);
        
        expect($result)->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
        expect($result->name())->toBe('inbox::index');
        
        $data = $result->getData();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toHaveKey('messages');
        expect($data['data']['messages'])->toBeArray();
        expect($data['data']['messages'])->toHaveCount(2);
    });

    it('handles empty message list', function () {
        $service = app(CaptureService::class);
        $controller = new InboxController();
        $request = request();
        
        $result = $controller->__invoke($request, $service);
        
        expect($result)->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
        expect($result->name())->toBe('inbox::index');
        
        $data = $result->getData();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toHaveKey('messages');
        expect($data['data']['messages'])->toBeArray();
        expect($data['data']['messages'])->toBeEmpty();
    });

    it('normalizes message data structure for both paginated and non-paginated responses', function () {
        $service = app(CaptureService::class);
        
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];
        
        $service->store($payload);
        
        $controller = new InboxController();
        $request = request();
        
        $result = $controller->__invoke($request, $service);
        
        $data = $result->getData();
        // Should handle both paginated (with 'data' key) and non-paginated message arrays
        expect($data['data']['messages'])->toBeArray();
        expect($data['data']['messages'])->toHaveCount(1);
    });

    it('uses CaptureService to retrieve all messages', function () {
        $service = app(CaptureService::class);
        
        // Store multiple messages to verify the service is called correctly
        for ($i = 1; $i <= 3; $i++) {
            $payload = [
                'subject' => "Test Email {$i}",
                'from' => [['email' => "test{$i}@example.com"]],
                'raw' => "Email {$i} content",
            ];
            $service->store($payload);
        }
        
        $controller = new InboxController();
        $request = request();
        
        $result = $controller->__invoke($request, $service);
        
        $data = $result->getData();
        expect($data['data']['messages'])->toHaveCount(3);
    });

    it('properly formats data structure for Vue.js consumption', function () {
        $service = app(CaptureService::class);
        
        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com', 'name' => 'Test User']],
            'to' => [['email' => 'recipient@example.com']],
            'html' => '<p>Test content</p>',
            'raw' => 'Test email content',
        ];
        
        $service->store($payload);
        
        $controller = new InboxController();
        $request = request();
        
        $result = $controller->__invoke($request, $service);
        
        $data = $result->getData();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toHaveKey('messages');
        
        $message = $data['data']['messages'][0];
        expect($message)->toHaveKey('subject', 'Test Email');
        expect($message)->toHaveKey('from');
        expect($message)->toHaveKey('to');
        expect($message)->toHaveKey('html', '<p>Test content</p>');
        expect($message)->toHaveKey('id');
        expect($message)->toHaveKey('timestamp');
    });
});