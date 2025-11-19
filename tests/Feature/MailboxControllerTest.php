<?php

use Inertia\Testing\AssertableInertia as Assert;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\MailboxController;

describe(MailboxController::class, function () {
    beforeEach(function () {
        config()->set('mailbox.public', true);

        // Clear any existing messages before each test
        $service = app(CaptureService::class);
        $service->clearAll();
    });

    it('returns inertia response with paginated list of messages sorted newest-first', function () {
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

        $response = $this->get(route('mailbox.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('messages', 2)
            ->has('title')
            ->has('subtitle')
            ->has('pagination')
            ->has('pagination.total')
            ->has('pagination.per_page')
            ->has('pagination.current_page')
            ->has('pagination.has_more')
            ->has('pagination.latest_timestamp')
            ->has('polling')
            ->has('polling.enabled')
            ->has('polling.interval')
        );
    });

    it('handles empty message list', function () {
        $response = $this->get(route('mailbox.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('messages', 0)
            ->has('pagination')
            ->has('polling')
        );
    });

    it('normalizes message data structure for both paginated and non-paginated responses', function () {
        $service = app(CaptureService::class);

        $payload = [
            'subject' => 'Test Email',
            'from' => [['email' => 'test@example.com']],
            'raw' => 'Test email content',
        ];

        $service->store($payload);

        $response = $this->get(route('mailbox.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('messages', 1)
            ->has('pagination')
            ->has('polling')
        );
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

        $response = $this->get(route('mailbox.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('messages', 3)
            ->has('pagination')
            ->has('polling')
        );
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

        $response = $this->get(route('mailbox.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('messages', 1)
            ->has('messages.0.subject')
            ->where('messages.0.subject', 'Test Email')
            ->has('messages.0.from')
            ->has('messages.0.to')
            ->has('messages.0.html_body')
            ->has('messages.0.id')
            ->has('messages.0.created_at')
            ->has('pagination')
            ->has('polling')
        );
    });

    it('returns pagination metadata with has_more flag', function () {
        $service = app(CaptureService::class);

        // Store 5 messages
        for ($i = 1; $i <= 5; $i++) {
            $service->store([
                'subject' => "Test Email {$i}",
                'from' => [['email' => "test{$i}@example.com"]],
                'raw' => "Email {$i} content",
                'timestamp' => 1000 + $i,
            ]);
        }

        // Request first page with 2 items per page
        $response = $this->get(route('mailbox.index', ['page' => 1, 'per_page' => 2]));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('messages', 2)
            ->has('pagination', fn (Assert $pagination) => $pagination
                ->where('total', 5)
                ->where('per_page', 2)
                ->where('current_page', 1)
                ->where('has_more', true)
                ->has('latest_timestamp')
            )
        );
    });

    it('returns polling configuration from config', function () {
        config(['mailbox.polling.enabled' => true]);
        config(['mailbox.polling.interval' => 3000]);

        $response = $this->get(route('mailbox.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('mailbox::Dashboard')
            ->has('polling', fn (Assert $polling) => $polling
                ->where('enabled', true)
                ->where('interval', 3000)
            )
        );
    });
});
