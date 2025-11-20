<?php

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\Models\MailboxAttachment;
use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;

beforeEach(function () {
    // Set up test database connection
    config(['mailbox.store.database.connection' => 'testing']);

    // Run migrations
    $this->artisan('migrate', ['--database' => 'testing'])->run();

    // Configure mailbox disk for testing
    Storage::fake('mailbox');
    config(['filesystems.disks.mailbox' => [
        'driver' => 'local',
        'root' => Storage::disk('mailbox')->path(''),
    ]]);
});

describe(AttachmentStore::class, function () {
    it('stores attachment metadata and file', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
            'subject' => 'Test',
        ]);

        $attachmentData = new AttachmentData(
            filename: 'test.txt',
            mimeType: 'text/plain',
            size: 11,
            content: base64_encode('hello world'),
            cid: null,
            isInline: false
        );

        $store = new AttachmentStore;
        $attachment = $store->store($message->id, $attachmentData);

        expect($attachment)->toBeInstanceOf(MailboxAttachment::class)
            ->and($attachment->filename)->toBe('test.txt')
            ->and($attachment->mime_type)->toBe('text/plain')
            ->and($attachment->size)->toBe(11)
            ->and($attachment->is_inline)->toBeFalse();

        // Verify file was stored
        Storage::disk('mailbox')->assertExists($attachment->path);

        // Verify content
        $content = Storage::disk('mailbox')->get($attachment->path);
        expect($content)->toBe('hello world');
    });

    it('finds attachment by ID', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $attachment = MailboxAttachment::query()->create([
            'id' => 'test-id',
            'message_id' => $message->id,
            'filename' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'disk' => 'mailbox',
            'path' => 'attachments/test.txt',
            'is_inline' => false,
        ]);

        $store = new AttachmentStore;
        $found = $store->find('test-id');

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe('test-id')
            ->and($found->filename)->toBe('test.txt');
    });

    it('returns null for non-existent attachment', function () {
        $store = new AttachmentStore;
        $found = $store->find('non-existent-id');

        expect($found)->toBeNull();
    });

    it('finds attachments by message ID', function () {
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
            'filename' => 'file2.txt',
            'mime_type' => 'text/plain',
            'size' => 200,
            'disk' => 'mailbox',
            'path' => 'attachments/file2.txt',
            'is_inline' => false,
        ]);

        $store = new AttachmentStore;
        $attachments = $store->findByMessage($message->id);

        expect($attachments)->toHaveCount(2)
            ->and($attachments[0]->filename)->toBe('file1.txt')
            ->and($attachments[1]->filename)->toBe('file2.txt');
    });

    it('finds attachment by CID', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'image.png',
            'mime_type' => 'image/png',
            'size' => 1000,
            'disk' => 'mailbox',
            'path' => 'attachments/image.png',
            'cid' => 'img123@example.com',
            'is_inline' => true,
        ]);

        $store = new AttachmentStore;
        $attachment = $store->findByCid($message->id, 'img123@example.com');

        expect($attachment)->not->toBeNull()
            ->and($attachment->cid)->toBe('img123@example.com')
            ->and($attachment->is_inline)->toBeTrue();
    });

    it('deletes attachment and file', function () {
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

        $store = new AttachmentStore;
        $store->delete($attachment);

        // Verify metadata deleted
        expect(MailboxAttachment::query()->find('att1'))->toBeNull();

        // Verify file deleted
        Storage::disk('mailbox')->assertMissing('attachments/test.txt');
    });

    it('deletes all attachments for a message', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        Storage::disk('mailbox')->put('attachments/file1.txt', 'content1');
        Storage::disk('mailbox')->put('attachments/file2.txt', 'content2');

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'file1.txt',
            'mime_type' => 'text/plain',
            'size' => 8,
            'disk' => 'mailbox',
            'path' => 'attachments/file1.txt',
            'is_inline' => false,
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att2',
            'message_id' => $message->id,
            'filename' => 'file2.txt',
            'mime_type' => 'text/plain',
            'size' => 8,
            'disk' => 'mailbox',
            'path' => 'attachments/file2.txt',
            'is_inline' => false,
        ]);

        $store = new AttachmentStore;
        $store->deleteByMessage($message->id);

        expect(MailboxAttachment::query()->where('message_id', $message->id)->count())->toBe(0);
        Storage::disk('mailbox')->assertMissing('attachments/file1.txt');
        Storage::disk('mailbox')->assertMissing('attachments/file2.txt');
    });

    it('deletes all attachments', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        Storage::disk('mailbox')->put('attachments/file.txt', 'content');

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'file.txt',
            'mime_type' => 'text/plain',
            'size' => 7,
            'disk' => 'mailbox',
            'path' => 'attachments/file.txt',
            'is_inline' => false,
        ]);

        $store = new AttachmentStore;
        $store->deleteAll();

        expect(MailboxAttachment::query()->count())->toBe(0);
        Storage::disk('mailbox')->assertDirectoryEmpty('');
    });

    it('retrieves file content for attachment', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        Storage::disk('mailbox')->put('attachments/test.txt', 'file content here');

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 17,
            'disk' => 'mailbox',
            'path' => 'attachments/test.txt',
            'is_inline' => false,
        ]);

        $store = new AttachmentStore;
        $content = $store->getContent($attachment);

        expect($content)->toBe('file content here');
    });

    it('handles storing attachment with non-base64 content', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $attachmentData = new AttachmentData(
            filename: 'test.txt',
            mimeType: 'text/plain',
            size: 11,
            content: 'plain text',
            cid: null,
            isInline: false
        );

        $store = new AttachmentStore;
        $attachment = $store->store($message->id, $attachmentData);

        $content = Storage::disk('mailbox')->get($attachment->path);
        expect($content)->toBe('plain text');
    });
});
