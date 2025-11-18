<?php

use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(FileStorage::class, function () {
    function storage(): FileStorage
    {
        $tmp = sys_get_temp_dir().'/mailbox-fs-tests-'.uniqid();
        @mkdir($tmp, 0777, true);

        return new FileStorage($tmp);
    }

    it('writes and retrieves a payload', function () {
        $store = storage();
        $id = $store->store(['id' => 'test-id', 'raw' => 'foo', 'timestamp' => 1]);

        expect($store->find('test-id')['raw'])->toBe('foo');
    });

    it('generates id when not provided', function () {
        $store = storage();
        $id = $store->store(['raw' => 'bar', 'timestamp' => time()]);

        expect($id)->not->toBeEmpty();
        expect($store->find($id))->not->toBeNull();
    });

    it('lists stored messages via paginate', function () {
        $store = storage();
        $store->store(['id' => 'one', 'raw' => '1', 'timestamp' => 1]);
        $store->store(['id' => 'two', 'raw' => '2', 'timestamp' => 2]);

        $results = $store->paginate(1, 10);
        expect($results)->toHaveCount(2);
        expect(array_column($results, 'id'))->toContain('one', 'two');
    });

    it('orders messages by timestamp desc in paginate', function () {
        $store = storage();
        $store->store(['id' => 'old', 'raw' => 'old', 'timestamp' => 1000]);
        $store->store(['id' => 'new', 'raw' => 'new', 'timestamp' => 2000]);

        $results = $store->paginate(1, 10);
        expect($results[0]['id'])->toBe('new')
            ->and($results[1]['id'])->toBe('old');
    });

    it('deletes a payload', function () {
        $store = storage();
        $id = $store->store(['id' => 'delete-me', 'raw' => 'bar', 'timestamp' => 1]);
        $store->delete($id);

        expect($store->find($id))->toBeNull();
    });

    it('purges old payloads', function () {
        $store = storage();
        $oldId = $store->store(['id' => 'old', 'raw' => 'x', 'timestamp' => time() - 100]);
        $newId = $store->store(['id' => 'new', 'raw' => 'y', 'timestamp' => time()]);

        $store->purgeOlderThan(50);
        
        expect($store->find($oldId))->toBeNull()
            ->and($store->find($newId))->not->toBeNull();
    });

    it('handles update on non-existent key', function () {
        $store = storage();

        $result = $store->update('nonexistent', ['seen_at' => 'now']);
        expect($result)->toBeNull();
    });

    it('successfully updates existing key', function () {
        $store = storage();
        $id = $store->store(['id' => 'test', 'raw' => 'test', 'timestamp' => 1000, 'seen_at' => null]);

        $updated = $store->update($id, ['seen_at' => '2024-01-01']);

        expect($updated)->toHaveKey('seen_at', '2024-01-01')
            ->and($updated)->toHaveKey('raw', 'test');
    });

    it('clears all messages', function () {
        $store = storage();
        $store->store(['id' => 'one', 'raw' => '1', 'timestamp' => 1]);
        $store->store(['id' => 'two', 'raw' => '2', 'timestamp' => 2]);

        $store->clear();

        expect($store->paginate(1, 10))->toBeEmpty();
    });

    it('paginates correctly with multiple pages', function () {
        $store = storage();
        $store->store(['id' => 'msg1', 'raw' => '1', 'timestamp' => 1]);
        $store->store(['id' => 'msg2', 'raw' => '2', 'timestamp' => 2]);
        $store->store(['id' => 'msg3', 'raw' => '3', 'timestamp' => 3]);

        $page1 = $store->paginate(1, 2);
        $page2 = $store->paginate(2, 2);

        expect($page1)->toHaveCount(2)
            ->and($page2)->toHaveCount(1);
    });
});
