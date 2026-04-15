<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;
use Redberry\MailboxForLaravel\Models\MailboxAttachment;
use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\DatabaseAttachmentStore;

beforeEach(function () {
    config(['mailbox.store.database.connection' => 'testing']);
    $this->artisan('migrate', ['--database' => 'testing'])->run();

    Storage::fake('mailbox');
    config(['filesystems.disks.mailbox' => [
        'driver' => 'local',
        'root' => Storage::disk('mailbox')->path(''),
    ]]);
});

describe(DatabaseAttachmentStore::class, function () {
    it('persists metadata in the mailbox_attachments table', function () {
        MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $store = new DatabaseAttachmentStore;

        $attachment = $store->store(1, new AttachmentData(
            filename: 'test.txt',
            mimeType: 'text/plain',
            size: 11,
            content: base64_encode('hello world'),
            cid: null,
            isInline: false,
        ));

        expect($attachment)->toBeInstanceOf(StoredAttachment::class);

        $row = MailboxAttachment::query()->find($attachment->id);
        expect($row)->not->toBeNull()
            ->and($row->filename)->toBe('test.txt')
            ->and($row->mime_type)->toBe('text/plain');
    });

    it('cascades attachment rows when the message row is deleted', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'a.txt',
            'mime_type' => 'text/plain',
            'size' => 1,
            'disk' => 'mailbox',
            'path' => 'attachments/a.txt',
            'is_inline' => false,
        ]);

        $message->delete();

        expect(MailboxAttachment::query()->find('att1'))->toBeNull();
    });
});
