<?php

use Illuminate\Support\Str;
use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\DatabaseMessageStore;

describe(DatabaseMessageStore::class, function () {
    function ulid(): string
    {
        return (string) Str::ulid();
    }

    it('stores a message and returns id', function () {
        $store = new DatabaseMessageStore;
        $id = ulid();

        $returned = $store->store([
            'id' => $id,
            'raw' => 'email content',
            'timestamp' => time(),
            'subject' => 'Test',
        ]);

        expect($returned)->toBe($id);
        expect(MailboxMessage::query()->find($id))->not->toBeNull();
    });

    it('throws when the payload is missing an id', function () {
        $store = new DatabaseMessageStore;

        expect(fn () => $store->store([
            'raw' => 'content without id',
            'timestamp' => time(),
        ]))->toThrow(InvalidArgumentException::class);
    });

    it('finds a message by id', function () {
        $store = new DatabaseMessageStore;
        $id = ulid();

        $store->store([
            'id' => $id,
            'raw' => 'findable',
            'timestamp' => 1000,
        ]);

        $result = $store->find($id);
        expect($result)->toBeArray()
            ->and($result['raw'])->toBe('findable');
    });

    it('returns null for non-existent id', function () {
        $store = new DatabaseMessageStore;
        $result = $store->find('does-not-exist');

        expect($result)->toBeNull();
    });

    it('paginates messages ordered by timestamp desc', function () {
        $store = new DatabaseMessageStore;
        $old = $store->store(['id' => ulid(), 'raw' => 'old', 'timestamp' => 1000]);
        $new = $store->store(['id' => ulid(), 'raw' => 'new', 'timestamp' => 2000]);
        $mid = $store->store(['id' => ulid(), 'raw' => 'mid', 'timestamp' => 1500]);

        $results = $store->paginate(1, 10);

        expect($results)->toHaveCount(3)
            ->and($results[0]['id'])->toBe($new)
            ->and($results[1]['id'])->toBe($mid)
            ->and($results[2]['id'])->toBe($old);
    });

    it('paginates correctly with multiple pages', function () {
        $store = new DatabaseMessageStore;
        $store->store(['id' => ulid(), 'raw' => '1', 'timestamp' => 3000]);
        $store->store(['id' => ulid(), 'raw' => '2', 'timestamp' => 2000]);
        $oldest = $store->store(['id' => ulid(), 'raw' => '3', 'timestamp' => 1000]);

        $page1 = $store->paginate(1, 2);
        $page2 = $store->paginate(2, 2);

        expect($page1)->toHaveCount(2)
            ->and($page2)->toHaveCount(1)
            ->and($page2[0]['id'])->toBe($oldest);
    });

    it('updates existing message', function () {
        $store = new DatabaseMessageStore;
        $id = $store->store([
            'id' => ulid(),
            'raw' => 'original',
            'timestamp' => 1000,
        ]);

        $updated = $store->update($id, ['seen_at' => '2024-01-01']);

        expect($updated)->toBeArray()
            ->and($updated['raw'])->toBe('original')
            ->and($updated['seen_at'])->not->toBeNull();
    });

    it('returns null when updating non-existent message', function () {
        $store = new DatabaseMessageStore;
        $result = $store->update('not-there', ['seen_at' => 'now']);

        expect($result)->toBeNull();
    });

    it('deletes a message', function () {
        $store = new DatabaseMessageStore;
        $deleteMeId = $store->store(['id' => ulid(), 'raw' => 'bye', 'timestamp' => 1000]);

        $store->delete($deleteMeId);

        expect($store->find($deleteMeId))->toBeNull();
    });

    it('purges old messages', function () {
        $store = new DatabaseMessageStore;
        $oldId = $store->store(['id' => ulid(), 'raw' => 'old', 'timestamp' => time() - 100]);
        $newId = $store->store(['id' => ulid(), 'raw' => 'new', 'timestamp' => time()]);

        $store->purgeOlderThan(50);

        expect($store->find($oldId))->toBeNull()
            ->and($store->find($newId))->not->toBeNull();
    });

    it('clears all messages', function () {
        $store = new DatabaseMessageStore;
        $store->store(['id' => ulid(), 'raw' => '1', 'timestamp' => 1000]);
        $store->store(['id' => ulid(), 'raw' => '2', 'timestamp' => 2000]);

        $store->clear();

        expect($store->paginate(1, 10))->toBeEmpty();
    });

    it('finds existing id by RFC message_id', function () {
        $store = new DatabaseMessageStore;
        $id = ulid();

        $store->store([
            'id' => $id,
            'raw' => 'content',
            'timestamp' => time(),
            'message_id' => '<abc123@example.com>',
        ]);

        expect($store->findIdByMessageId('<abc123@example.com>'))->toBe($id);
    });

    it('returns null for unknown message_id', function () {
        $store = new DatabaseMessageStore;

        expect($store->findIdByMessageId('<unknown@example.com>'))->toBeNull();
    });

    it('handles updateOrCreate when storing with existing id', function () {
        $store = new DatabaseMessageStore;
        $existingId = $store->store([
            'id' => ulid(),
            'raw' => 'first version',
            'timestamp' => 1000,
        ]);

        $store->store([
            'id' => $existingId,
            'raw' => 'updated version',
            'timestamp' => 2000,
        ]);

        $result = $store->find($existingId);
        expect($result['raw'])->toBe('updated version')
            ->and($result['timestamp'])->toBe(2000);
    });
});
