<?php

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\MailboxController;

describe(MailboxController::class, function () {
    beforeEach(function () {
        config()->set('mailbox.public', true);

        $service = app(CaptureService::class);
        $service->clearAll();
    });

    describe('HTML (initial page load)', function () {
        it('renders the mailbox::app view with embedded JSON payload', function () {
            $service = app(CaptureService::class);

            $service->store([
                'subject' => 'First Email',
                'timestamp' => 1000,
                'from' => [['email' => 'test1@example.com']],
                'raw' => 'Email 1',
            ]);
            $service->store([
                'subject' => 'Second Email',
                'timestamp' => 2000,
                'from' => [['email' => 'test2@example.com']],
                'raw' => 'Email 2',
            ]);

            $response = $this->get(route('mailbox.index'));

            $response->assertStatus(200);
            $response->assertViewIs('mailbox::app');
            $response->assertViewHas('data', function (array $data) {
                return count($data['messages']) === 2
                    && isset($data['pagination'])
                    && isset($data['polling'])
                    && isset($data['title'])
                    && isset($data['subtitle'])
                    && isset($data['mailboxPrefix'])
                    && array_key_exists('csrfToken', $data);
            });
        });

        it('renders an empty message list without errors', function () {
            $response = $this->get(route('mailbox.index'));

            $response->assertStatus(200);
            $response->assertViewIs('mailbox::app');
            $response->assertViewHas('data', function (array $data) {
                return $data['messages'] === []
                    && $data['pagination']['total'] === 0;
            });
        });
    });

    describe('JSON (AJAX requests)', function () {
        it('returns JSON payload when Accept: application/json is sent', function () {
            $service = app(CaptureService::class);

            $service->store([
                'subject' => 'JSON payload test',
                'from' => [['email' => 'test@example.com']],
                'raw' => 'raw',
            ]);

            $response = $this->getJson(route('mailbox.index'));

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'messages',
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'has_more',
                    'latest_timestamp',
                ],
                'polling' => ['enabled', 'interval'],
                'search',
                'mailboxPrefix',
                'csrfToken',
                'title',
                'subtitle',
            ]);
            $response->assertJsonCount(1, 'messages');
        });

        it('shapes each message for Vue.js consumption', function () {
            $service = app(CaptureService::class);

            $service->store([
                'subject' => 'Test Email',
                'from' => [['email' => 'test@example.com', 'name' => 'Test User']],
                'to' => [['email' => 'recipient@example.com']],
                'html' => '<p>Test content</p>',
                'raw' => 'Test email content',
            ]);

            $response = $this->getJson(route('mailbox.index'));

            $response->assertStatus(200);
            $response->assertJsonPath('messages.0.subject', 'Test Email');
            $response->assertJsonStructure([
                'messages' => [
                    '*' => ['id', 'subject', 'from', 'to', 'html_body', 'created_at', 'attachments'],
                ],
            ]);
        });

        it('returns pagination metadata with the has_more flag', function () {
            $service = app(CaptureService::class);

            for ($i = 1; $i <= 5; $i++) {
                $service->store([
                    'subject' => "Test Email {$i}",
                    'from' => [['email' => "test{$i}@example.com"]],
                    'raw' => "Email {$i}",
                    'timestamp' => 1000 + $i,
                ]);
            }

            $response = $this->getJson(route('mailbox.index', ['page' => 1, 'per_page' => 2]));

            $response->assertStatus(200);
            $response->assertJsonPath('pagination.total', 5);
            $response->assertJsonPath('pagination.per_page', 2);
            $response->assertJsonPath('pagination.current_page', 1);
            $response->assertJsonPath('pagination.has_more', true);
            $response->assertJsonCount(2, 'messages');
        });

        it('filters by search across subject, from, to, and text', function () {
            $service = app(CaptureService::class);

            $service->store([
                'subject' => 'Invoice for March',
                'timestamp' => 1000,
                'from' => [['email' => 'billing@acme.test']],
                'to' => [['email' => 'customer@example.com']],
                'text' => 'Your invoice is attached.',
                'raw' => 'raw-1',
            ]);
            $service->store([
                'subject' => 'Welcome aboard',
                'timestamp' => 2000,
                'from' => [['email' => 'hello@startup.test']],
                'to' => [['email' => 'user@example.com']],
                'text' => 'Thanks for signing up.',
                'raw' => 'raw-2',
            ]);
            $service->store([
                'subject' => 'Password reset',
                'timestamp' => 3000,
                'from' => [['email' => 'security@startup.test']],
                'to' => [['email' => 'user@example.com']],
                'text' => 'Click to reset your password. Reference: INV-42.',
                'raw' => 'raw-3',
            ]);

            $this->getJson(route('mailbox.index', ['search' => 'INVOICE']))
                ->assertJsonCount(1, 'messages')
                ->assertJsonPath('messages.0.subject', 'Invoice for March')
                ->assertJsonPath('pagination.total', 1)
                ->assertJsonPath('search', 'INVOICE');

            $this->getJson(route('mailbox.index', ['search' => 'reset your password']))
                ->assertJsonCount(1, 'messages')
                ->assertJsonPath('messages.0.subject', 'Password reset');

            $this->getJson(route('mailbox.index', ['search' => 'billing@acme.test']))
                ->assertJsonCount(1, 'messages')
                ->assertJsonPath('pagination.total', 1);

            $this->getJson(route('mailbox.index', ['search' => '   ']))
                ->assertJsonCount(3, 'messages')
                ->assertJsonPath('search', '');
        });

        it('returns polling configuration from config', function () {
            config(['mailbox.polling.enabled' => true]);
            config(['mailbox.polling.interval' => 3000]);

            $response = $this->getJson(route('mailbox.index'));

            $response->assertStatus(200);
            $response->assertJsonPath('polling.enabled', true);
            $response->assertJsonPath('polling.interval', 3000);
        });

        it('exposes the configured mailbox path as mailboxPrefix', function () {
            config()->set('mailbox.path', 'custom-inbox');

            $response = $this->getJson(route('mailbox.index'));

            $response->assertStatus(200);
            $response->assertJsonPath('mailboxPrefix', 'custom-inbox');
        });
    });
});
