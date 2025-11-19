<?php

use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\DatabaseMessageStore;

describe(DatabaseMessageStore::class, function () {
    it('stores a message and returns id', function () {
        $store = new DatabaseMessageStore;
        $id = $store->store([
            'id' => 'test-msg-1',
            'raw' => 'email content',
            'timestamp' => time(),
            'subject' => 'Test',
        ]);

        expect($id)->toBe('test-msg-1');
        expect(MailboxMessage::query()->find('test-msg-1'))->not->toBeNull();
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
        $store->store([
            'id' => 'find-me',
            'raw' => 'findable',
            'timestamp' => 1000,
        ]);

        $result = $store->find('find-me');
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
        $store->store(['id' => 'old', 'raw' => 'old', 'timestamp' => 1000]);
        $store->store(['id' => 'new', 'raw' => 'new', 'timestamp' => 2000]);
        $store->store(['id' => 'mid', 'raw' => 'mid', 'timestamp' => 1500]);

        $results = $store->paginate(1, 10);

        expect($results)->toHaveCount(3);

        // Results are returned as array with numeric keys, ordered by timestamp desc
        expect($results[0]['id'])->toBe('new')
            ->and($results[1]['id'])->toBe('mid')
            ->and($results[2]['id'])->toBe('old');
    });

    it('paginates correctly with multiple pages', function () {
        $store = new DatabaseMessageStore;
        $store->store(['id' => 'msg1', 'raw' => '1', 'timestamp' => 3000]);
        $store->store(['id' => 'msg2', 'raw' => '2', 'timestamp' => 2000]);
        $store->store(['id' => 'msg3', 'raw' => '3', 'timestamp' => 1000]);

        $page1 = $store->paginate(1, 2);
        $page2 = $store->paginate(2, 2);

        expect($page1)->toHaveCount(2)
            ->and($page2)->toHaveCount(1)
            ->and($page2[0]['id'])->toBe('msg3');
    });

    it('updates existing message', function () {
        $store = new DatabaseMessageStore;
        $store->store([
            'id' => 'update-me',
            'raw' => 'original',
            'timestamp' => 1000,
        ]);

        $updated = $store->update('update-me', ['seen_at' => '2024-01-01']);

        expect($updated)->toBeArray()
            ->and($updated['raw'])->toBe('original');

        // seen_at gets cast to datetime by the model
        expect($updated['seen_at'])->not->toBeNull();
    });

    it('returns null when updating non-existent message', function () {
        $store = new DatabaseMessageStore;
        $result = $store->update('not-there', ['seen_at' => 'now']);

        expect($result)->toBeNull();
    });

    it('deletes a message', function () {
        $store = new DatabaseMessageStore;
        $store->store(['id' => 'delete-me', 'raw' => 'bye', 'timestamp' => 1000]);

        $store->delete('delete-me');

        expect($store->find('delete-me'))->toBeNull();
    });

    it('purges old messages', function () {
        $store = new DatabaseMessageStore;
        $oldId = $store->store(['id' => 'old', 'raw' => 'old', 'timestamp' => time() - 100]);
        $newId = $store->store(['id' => 'new', 'raw' => 'new', 'timestamp' => time()]);

        $store->purgeOlderThan(50);

        expect($store->find($oldId))->toBeNull()
            ->and($store->find($newId))->not->toBeNull();
    });

    it('clears all messages', function () {
        $store = new DatabaseMessageStore;
        $store->store(['id' => 'msg1', 'raw' => '1', 'timestamp' => 1000]);
        $store->store(['id' => 'msg2', 'raw' => '2', 'timestamp' => 2000]);

        $store->clear();

        expect($store->paginate(1, 10))->toBeEmpty();
    });

    it('handles updateOrCreate when storing with existing id', function () {
        $store = new DatabaseMessageStore;
        $store->store([
            'id' => 'existing',
            'raw' => 'first version',
            'timestamp' => 1000,
        ]);

        $store->store([
            'id' => 'existing',
            'raw' => 'updated version',
            'timestamp' => 2000,
        ]);

        $result = $store->find('existing');
        expect($result['raw'])->toBe('updated version')
            ->and($result['timestamp'])->toBe(2000);
    });
});
