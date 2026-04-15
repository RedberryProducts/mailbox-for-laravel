<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;
use Redberry\MailboxForLaravel\Storage\FileAttachmentStore;

beforeEach(function () {
    Storage::fake('mailbox');

    $this->basePath = sys_get_temp_dir().'/mailbox-attachments-'.uniqid();
    @mkdir($this->basePath, 0777, true);

    $this->store = new FileAttachmentStore($this->basePath, 'mailbox', 'attachments');
});

describe(FileAttachmentStore::class, function () {
    it('writes a JSON sidecar per message and a content file on disk', function () {
        $stored = $this->store->store('msg-1', new AttachmentData(
            filename: 'doc.txt',
            mimeType: 'text/plain',
            size: 5,
            content: 'hello',
            cid: null,
            isInline: false,
        ));

        expect($stored)->toBeInstanceOf(StoredAttachment::class);

        $sidecar = $this->basePath.DIRECTORY_SEPARATOR.'msg-1.json';
        expect(is_file($sidecar))->toBeTrue();

        Storage::disk('mailbox')->assertExists($stored->path);
        expect(Storage::disk('mailbox')->get($stored->path))->toBe('hello');
    });

    it('appends multiple attachments to the same message sidecar', function () {
        $this->store->store('msg-1', new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $this->store->store('msg-1', new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

        $records = $this->store->findByMessage('msg-1');

        expect($records)->toHaveCount(2)
            ->and($records[0]->filename)->toBe('a.txt')
            ->and($records[1]->filename)->toBe('b.txt');
    });

    it('removes the sidecar when all attachments for a message are deleted', function () {
        $stored = $this->store->store('msg-1', new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));

        $this->store->delete($stored);

        expect(is_file($this->basePath.DIRECTORY_SEPARATOR.'msg-1.json'))->toBeFalse();
    });

    it('clears every sidecar and content file', function () {
        $this->store->store('msg-1', new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $this->store->store('msg-2', new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

        $this->store->clear();

        expect(glob($this->basePath.'/*.json'))->toBe([]);
        Storage::disk('mailbox')->assertDirectoryEmpty('');
    });
});
