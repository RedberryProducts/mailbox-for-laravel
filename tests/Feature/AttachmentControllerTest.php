<?php

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\Models\MailboxAttachment;
use Redberry\MailboxForLaravel\Models\MailboxMessage;

beforeEach(function () {
    config(['mailbox.store.database.connection' => 'testing']);
    $this->artisan('migrate', ['--database' => 'testing'])->run();

    Storage::fake('mailbox');
    config(['filesystems.disks.mailbox' => [
        'driver' => 'local',
        'root' => Storage::disk('mailbox')->path(''),
    ]]);
});

describe('AttachmentController', function () {
    it('downloads attachment with correct headers', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
            'subject' => 'Test',
        ]);

        Storage::disk('mailbox')->put('attachments/test.txt', 'file content');

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att123',
            'message_id' => $message->id,
            'filename' => 'document.txt',
            'mime_type' => 'text/plain',
            'size' => 12,
            'disk' => 'mailbox',
            'path' => 'attachments/test.txt',
            'is_inline' => false,
        ]);

        $response = $this->get(route('mailbox.attachments.download', ['id' => 'att123']));

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename="document.txt"')
            ->assertHeader('Content-Length', '12');

        // Check that Content-Type starts with expected mime type (may include charset)
        expect($response->headers->get('Content-Type'))->toStartWith('text/plain')
            ->and($response->getContent())->toBe('file content');
    });

    it('views attachment inline with correct headers', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        Storage::disk('mailbox')->put('attachments/image.png', 'image data');

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att456',
            'message_id' => $message->id,
            'filename' => 'photo.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'disk' => 'mailbox',
            'path' => 'attachments/image.png',
            'is_inline' => true,
        ]);

        $response = $this->get(route('mailbox.attachments.inline', ['id' => 'att456']));

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', 'inline; filename="photo.png"');

        // Check Content-Type starts with expected mime type
        expect($response->headers->get('Content-Type'))->toStartWith('image/png')
            ->and($response->getContent())->toBe('image data');
    });

    it('returns 404 for non-existent attachment', function () {
        $response = $this->get(route('mailbox.attachments.download', ['id' => 'non-existent']));

        $response->assertStatus(404);
    });

    it('returns 404 when attachment file is missing', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att789',
            'message_id' => $message->id,
            'filename' => 'missing.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'disk' => 'mailbox',
            'path' => 'attachments/missing.txt',
            'is_inline' => false,
        ]);

        // File was not created in storage
        $response = $this->get(route('mailbox.attachments.download', ['id' => 'att789']));

        $response->assertStatus(404);
    });

    it('lists attachments for a message', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'file1.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'disk' => 'mailbox',
            'path' => 'attachments/file1.txt',
            'is_inline' => false,
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att2',
            'message_id' => $message->id,
            'filename' => 'file2.pdf',
            'mime_type' => 'application/pdf',
            'size' => 200,
            'disk' => 'mailbox',
            'path' => 'attachments/file2.pdf',
            'is_inline' => false,
        ]);

        $response = $this->getJson(route('mailbox.messages.attachments', ['messageId' => 1]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'attachments' => [
                    '*' => [
                        'id',
                        'filename',
                        'mime_type',
                        'size',
                        'is_inline',
                        'cid',
                        'download_url',
                        'inline_url',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'attachments');

        $data = $response->json();
        expect($data['attachments'][0]['filename'])->toBe('file1.txt')
            ->and($data['attachments'][1]['filename'])->toBe('file2.pdf');
    });

    it('requires authorization to access attachments', function () {
        // Mock Gate to deny access
        Gate::define('viewMailbox', fn () => false);

        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        Storage::disk('mailbox')->put('attachments/test.txt', 'content');

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 7,
            'disk' => 'mailbox',
            'path' => 'attachments/test.txt',
            'is_inline' => false,
        ]);

        $response = $this->get(route('mailbox.attachments.download', ['id' => 'att1']));

        $response->assertStatus(403);
    });
});
