<?php

use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\DatabaseMessageStore;

describe(DatabaseMessageStore::class, function () {
    it('stores a message and returns id', function () {
        $store = new DatabaseMessageStore;
        $id = $store->store([
            'raw' => 'email content',
            'timestamp' => time(),
            'subject' => 'Test',
        ]);

        expect(MailboxMessage::query()->find($id))->not->toBeNull();
    });

    it('generates id when not provided', function () {
        $store = new DatabaseMessageStore;

        // DatabaseMessageStore now generates IDs like FileStorage
        $id = $store->store([
            'raw' => 'content without id',
            'timestamp' => time(),
        ]);

        expect($id)->not->toBeEmpty();
        expect($store->find($id))->not->toBeNull();
    });

    it('finds a message by id', function () {
        $store = new DatabaseMessageStore;

        $messageId = $store->store([
            'id' => null,
            'raw' => 'findable',
            'timestamp' => 1000,
        ]);

        $result = $store->find($messageId);
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
        $old = $store->store(['raw' => 'old', 'timestamp' => 1000]);
        $new = $store->store(['raw' => 'new', 'timestamp' => 2000]);
        $mid = $store->store(['raw' => 'mid', 'timestamp' => 1500]);

        $results = $store->paginate(1, 10);

        expect($results)->toHaveCount(3)
            ->and($results[0]['id'])->toBe($new)
            ->and($results[1]['id'])->toBe($mid)
            ->and($results[2]['id'])->toBe($old);

        // Results are returned as array with numeric keys, ordered by timestamp desc
    });

    it('paginates correctly with multiple pages', function () {
        $store = new DatabaseMessageStore;
        $store->store(['raw' => '1', 'timestamp' => 3000]);
        $store->store(['raw' => '2', 'timestamp' => 2000]);
        $oldest = $store->store(['raw' => '3', 'timestamp' => 1000]);

        $page1 = $store->paginate(1, 2);
        $page2 = $store->paginate(2, 2);

        expect($page1)->toHaveCount(2)
            ->and($page2)->toHaveCount(1)
            ->and($page2[0]['id'])->toBe($oldest);
    });

    it('updates existing message', function () {
        $store = new DatabaseMessageStore;
        $id = $store->store([
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
        $deleteMeId = $store->store(['raw' => 'bye', 'timestamp' => 1000]);

        $store->delete($deleteMeId);

        expect($store->find($deleteMeId))->toBeNull();
    });

    it('purges old messages', function () {
        $store = new DatabaseMessageStore;
        $oldId = $store->store(['raw' => 'old', 'timestamp' => time() - 100]);
        $newId = $store->store(['raw' => 'new', 'timestamp' => time()]);

        $store->purgeOlderThan(50);

        expect($store->find($oldId))->toBeNull()
            ->and($store->find($newId))->not->toBeNull();
    });

    it('clears all messages', function () {
        $store = new DatabaseMessageStore;
        $store->store(['raw' => '1', 'timestamp' => 1000]);
        $store->store(['raw' => '2', 'timestamp' => 2000]);

        $store->clear();

        expect($store->paginate(1, 10))->toBeEmpty();
    });

    it('handles updateOrCreate when storing with existing id', function () {
        $store = new DatabaseMessageStore;
        $existingId = $store->store([
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
