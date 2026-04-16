<?php

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\DTO\PaginatedMessages;
use Redberry\MailboxForLaravel\Storage\FileAttachmentStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(CaptureService::class, function () {
    function service(): CaptureService
    {
        $path = sys_get_temp_dir().'/mailbox-capture-tests-'.uniqid();
        $store = new FileStorage($path);

        return new CaptureService($store);
    }

    it('stores raw message and returns key', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'hello']);

        expect($key)->not->toBeEmpty();
        $msg = $svc->find($key);
        expect($msg)->toBeInstanceOf(MailboxMessageData::class);
        expect($msg->raw)->toBe('hello');
    });

    it('generates a canonical ULID when the payload omits an id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'hello']);

        expect($key)->toBeString()
            ->and($key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });

    it('preserves a caller-supplied id verbatim', function () {
        $svc = service();
        $id = '01HZYX9KQJ3N0YCMA4V7XJX4PX';

        $returned = $svc->store(['id' => $id, 'raw' => 'replay']);

        expect($returned)->toBe($id);
        expect($svc->find($id)?->raw)->toBe('replay');
    });

    it('lists all messages ordered by timestamp desc', function () {
        $svc = service();
        $svc->store(['raw' => 'one', 'timestamp' => 1000]);
        $svc->store(['raw' => 'two', 'timestamp' => 2000]);

        $all = $svc->all();
        expect(count($all))->toBe(2);
        expect($all[0]->raw)->toBe('two'); // newest first
        expect($all[1]->raw)->toBe('one');
    });

    it('finds a message by id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'foo']);

        $msg = $svc->find($key);
        expect($msg)->toBeInstanceOf(MailboxMessageData::class);
        expect($msg->raw)->toBe('foo');
    });

    it('deletes a message by id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'bar']);
        $svc->delete($key);

        expect($svc->find($key))->toBeNull();
    });

    it('stores raw string directly using storeRaw method', function () {
        $svc = service();
        $key = $svc->storeRaw('raw email content');

        expect($key)->not->toBeEmpty();
        $retrieved = $svc->find($key);
        expect($retrieved)->toBeInstanceOf(MailboxMessageData::class);
        expect($retrieved->raw)->toBe('raw email content');
    });

    it('returns all messages when list called with default perPage', function () {
        $svc = service();
        $svc->store(['raw' => 'message1']);
        $svc->store(['raw' => 'message2']);
        $svc->store(['raw' => 'message3']);

        $result = $svc->list();

        expect($result)->toBeInstanceOf(PaginatedMessages::class)
            ->and($result->data)->toHaveCount(3)
            ->and($result->data[0])->toBeInstanceOf(MailboxMessageData::class)
            ->and($result->total)->toBe(3)
            ->and($result->hasMore)->toBeFalse();
    });

    it('returns paginated results when perPage is specified', function () {
        $svc = service();
        $svc->store(['raw' => 'message1', 'timestamp' => 1000]);
        $svc->store(['raw' => 'message2', 'timestamp' => 2000]);
        $svc->store(['raw' => 'message3', 'timestamp' => 3000]);

        $result = $svc->list(1, 2);

        expect($result)->toBeInstanceOf(PaginatedMessages::class)
            ->and($result->data)->toHaveCount(2)
            ->and($result->data[0])->toBeInstanceOf(MailboxMessageData::class)
            ->and($result->total)->toBe(3)
            ->and($result->hasMore)->toBeTrue()
            ->and($result->latestTimestamp)->toBe(3000);
    });

    it('marks message as seen', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'test']);

        $updated = $svc->markSeen($key);

        expect($updated)->toBeInstanceOf(MailboxMessageData::class);
        expect($updated->seen_at)->not->toBeNull();
    });

    it('updates message with partial changes', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'test', 'custom_field' => 'old']);

        $updated = $svc->update($key, ['custom_field' => 'new']);

        expect($updated)->toBeInstanceOf(MailboxMessageData::class);
        expect($updated->raw)->toBe('test');
    });

    it('purges messages older than given seconds', function () {
        $svc = service();
        $oldKey = $svc->store(['raw' => 'old', 'timestamp' => time() - 100]);
        $newKey = $svc->store(['raw' => 'new', 'timestamp' => time()]);

        $svc->purgeOlderThan(50);

        expect($svc->find($oldKey))->toBeNull();
        expect($svc->find($newKey))->not->toBeNull();
    });

    it('clears all messages', function () {
        $svc = service();
        $svc->store(['raw' => 'one']);
        $svc->store(['raw' => 'two']);

        $svc->clearAll();

        expect($svc->all())->toBeEmpty();
    });

    describe('write-path idempotency', function () {
        it('second store with same message_id returns the same id', function () {
            $svc = service();

            $id1 = $svc->store(['raw' => 'v1', 'message_id' => '<dup@example.com>', 'subject' => 'First']);
            $id2 = $svc->store(['raw' => 'v2', 'message_id' => '<dup@example.com>', 'subject' => 'Second']);

            expect($id2)->toBe($id1);

            $msg = $svc->find($id1);
            expect($msg->subject)->toBe('Second');
        });

        it('null message_id never deduplicates', function () {
            $svc = service();

            $id1 = $svc->store(['raw' => 'one', 'message_id' => null]);
            $id2 = $svc->store(['raw' => 'two', 'message_id' => null]);

            expect($id1)->not->toBe($id2);
            expect($svc->all())->toHaveCount(2);
        });

        it('empty-string message_id never deduplicates', function () {
            $svc = service();

            $id1 = $svc->store(['raw' => 'one', 'message_id' => '']);
            $id2 = $svc->store(['raw' => 'two', 'message_id' => '']);

            expect($id1)->not->toBe($id2);
            expect($svc->all())->toHaveCount(2);
        });

        it('caller-supplied id takes precedence over message_id lookup', function () {
            $svc = service();

            $svc->store(['raw' => 'original', 'message_id' => '<x@example.com>']);

            $customId = '01CUSTOM0000000000000000ID';
            $returned = $svc->store(['id' => $customId, 'raw' => 'override', 'message_id' => '<x@example.com>']);

            expect($returned)->toBe($customId);
            expect($svc->all())->toHaveCount(2);
        });

        it('upserted message updates content', function () {
            $svc = service();

            $id = $svc->store(['raw' => 'v1', 'message_id' => '<up@example.com>', 'subject' => 'Version 1']);
            $svc->store(['raw' => 'v2', 'message_id' => '<up@example.com>', 'subject' => 'Version 2']);

            $msg = $svc->find($id);
            expect($msg->subject)->toBe('Version 2')
                ->and($msg->raw)->toBe('v2');
        });
    });

    describe('attachment cascade', function () {
        function withAttachments(): array
        {
            Storage::fake('mailbox');

            $messagePath = sys_get_temp_dir().'/mailbox-cascade-msgs-'.uniqid();
            $attachPath = sys_get_temp_dir().'/mailbox-cascade-atts-'.uniqid();

            $store = new FileStorage($messagePath);
            $attachments = new FileAttachmentStore($attachPath, 'mailbox', 'attachments');
            $svc = new CaptureService($store, $attachments);

            return [$svc, $attachments];
        }

        it('deletes attachments when a message is deleted', function () {
            [$svc, $attachments] = withAttachments();

            $id = $svc->store(['raw' => 'one']);
            $attachments->store($id, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));

            $svc->delete($id);

            expect($attachments->findByMessage($id))->toBe([]);
        });

        it('clears every attachment when all messages are cleared', function () {
            [$svc, $attachments] = withAttachments();

            $a = $svc->store(['raw' => 'one']);
            $b = $svc->store(['raw' => 'two']);
            $attachments->store($a, new AttachmentData('a.txt', 'text/plain', 1, 'a', null, false));
            $attachments->store($b, new AttachmentData('b.txt', 'text/plain', 1, 'b', null, false));

            $svc->clearAll();

            expect($attachments->findByMessage($a))->toBe([])
                ->and($attachments->findByMessage($b))->toBe([]);
        });

        it('cascades to attachments during purgeOlderThan', function () {
            [$svc, $attachments] = withAttachments();

            $old = $svc->store(['raw' => 'old', 'timestamp' => time() - 100]);
            $new = $svc->store(['raw' => 'new', 'timestamp' => time()]);
            $attachments->store($old, new AttachmentData('old.txt', 'text/plain', 1, 'a', null, false));
            $attachments->store($new, new AttachmentData('new.txt', 'text/plain', 1, 'b', null, false));

            $svc->purgeOlderThan(50);

            expect($attachments->findByMessage($old))->toBe([])
                ->and($attachments->findByMessage($new))->toHaveCount(1);
        });
    });
});
