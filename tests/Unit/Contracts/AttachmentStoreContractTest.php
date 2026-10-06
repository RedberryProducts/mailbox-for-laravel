<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;
use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\DatabaseAttachmentStore;
use Redberry\MailboxForLaravel\Storage\FileAttachmentStore;

beforeEach(function () {
    config(['mailbox.store.database.connection' => 'testing']);
    $this->artisan('migrate', ['--database' => 'testing'])->run();

    Storage::fake('mailbox');
    config(['filesystems.disks.mailbox' => [
        'driver' => 'local',
        'root' => Storage::disk('mailbox')->path(''),
    ]]);
});

dataset('attachment_stores', function () {
    return [
        'database' => [
            function (): AttachmentStore {
                MailboxMessage::query()->updateOrCreate(['id' => 1], [
                    'id' => 1,
                    'timestamp' => time(),
                ]);

                return new DatabaseAttachmentStore;
            },
        ],
        'file' => [
            function (): AttachmentStore {
                $base = sys_get_temp_dir().'/mailbox-attachments-contract-'.uniqid();
                @mkdir($base, 0777, true);

                return new FileAttachmentStore($base, 'mailbox', 'attachments');
            },
        ],
    ];
});

describe('AttachmentStore contract', function () {
    it('stores an attachment and finds it by id', function (Closure $factory) {
        $store = $factory();

        $stored = $store->store(1, new AttachmentData(
            filename: 'doc.txt',
            mimeType: 'text/plain',
            size: 5,
            content: 'hello',
            cid: null,
            isInline: false,
        ));

        $found = $store->find($stored->id);

        expect($found)->toBeInstanceOf(StoredAttachment::class)
            ->and($found->id)->toBe($stored->id)
            ->and($found->filename)->toBe('doc.txt');
    })->with('attachment_stores');

    it('finds attachments by message id', function (Closure $factory) {
        $store = $factory();

        $store->store(1, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $store->store(1, new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

        $records = $store->findByMessage(1);

        expect($records)->toHaveCount(2);
    })->with('attachment_stores');

    it('finds attachments for several messages at once, keyed by message id', function (Closure $factory) {
        $store = $factory();

        MailboxMessage::query()->updateOrCreate(['id' => 2], ['id' => 2, 'timestamp' => time()]);
        MailboxMessage::query()->updateOrCreate(['id' => 3], ['id' => 3, 'timestamp' => time()]);

        $store->store(1, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $store->store(1, new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));
        $store->store(2, new AttachmentData('c.txt', 'text/plain', 1, 'c', null, false));

        $records = $store->findByMessages([1, 2, 3]);

        expect($records)->toHaveCount(3)
            ->and(array_map(static fn (StoredAttachment $a): string => $a->filename, $records['1']))->toBe(['a.txt', 'b.txt'])
            ->and(array_map(static fn (StoredAttachment $a): string => $a->filename, $records['2']))->toBe(['c.txt'])
            ->and($records['3'])->toBe([])
            ->and($store->findByMessages([]))->toBe([]);
    })->with('attachment_stores');

    it('finds an inline attachment by cid', function (Closure $factory) {
        $store = $factory();

        $store->store(1, new AttachmentData('img.png', 'image/png', 3, 'png', 'cid-1@x', true));

        $found = $store->findByCid(1, 'cid-1@x');

        expect($found)->not->toBeNull()
            ->and($found->isInline)->toBeTrue()
            ->and($found->cid)->toBe('cid-1@x');
    })->with('attachment_stores');

    it('returns null when an attachment is missing', function (Closure $factory) {
        $store = $factory();

        expect($store->find('missing'))->toBeNull()
            ->and($store->findByCid(1, 'missing'))->toBeNull()
            ->and($store->findByMessage(1))->toBe([]);
    })->with('attachment_stores');

    it('round-trips content through getContent', function (Closure $factory) {
        $store = $factory();

        $stored = $store->store(1, new AttachmentData('a.txt', 'text/plain', 5, 'hello', null, false));

        expect($store->getContent($stored))->toBe('hello');
    })->with('attachment_stores');

    it('stores raw content verbatim even when it happens to be valid base64', function (Closure $factory, string $content) {
        $store = $factory();

        $stored = $store->store(1, new AttachmentData('token.txt', 'text/plain', strlen($content), $content, null, false));

        expect($store->getContent($stored))->toBe($content);
    })->with('attachment_stores')->with([
        'base64-looking text' => 'SGVsbG8=',
        'short token' => 'abcd',
        'pem body' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A\r\nMIIBCgKCAQEAu1SU1LfVLPHCozMxH2Mo",
    ]);

    it('deletes a single attachment without affecting siblings', function (Closure $factory) {
        $store = $factory();

        $a = $store->store(1, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $b = $store->store(1, new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

        $store->delete($a);

        expect($store->find($a->id))->toBeNull()
            ->and($store->find($b->id))->not->toBeNull();
    })->with('attachment_stores');

    it('deletes every attachment for a message', function (Closure $factory) {
        $store = $factory();

        $store->store(1, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $store->store(1, new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

        $store->deleteByMessage(1);

        expect($store->findByMessage(1))->toBe([]);
    })->with('attachment_stores');

    it('clears every attachment across all messages', function (Closure $factory) {
        $store = $factory();

        MailboxMessage::query()->updateOrCreate(['id' => 2], ['id' => 2, 'timestamp' => time()]);

        $store->store(1, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
        $store->store(2, new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

        $store->clear();

        expect($store->findByMessage(1))->toBe([])
            ->and($store->findByMessage(2))->toBe([]);
    })->with('attachment_stores');
});
