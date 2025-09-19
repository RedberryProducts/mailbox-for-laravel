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
        $store->store('a', ['raw' => 'foo', 'timestamp' => 1]);

        expect($store->retrieve('a')['raw'])->toBe('foo');
    });

    it('lists stored keys', function () {
        $store = storage();
        $store->store('one', ['raw' => '1', 'timestamp' => 1]);
        $store->store('two', ['raw' => '2', 'timestamp' => 2]);

        expect(iterator_to_array($store->keys()))->toContain('one', 'two');
    });

    it('deletes a payload', function () {
        $store = storage();
        $store->store('b', ['raw' => 'bar', 'timestamp' => 1]);
        $store->delete('b');

        expect($store->retrieve('b'))->toBeNull();
    });

    it('purges old payloads', function () {
        $store = storage();
        $store->store('old', ['raw' => 'x', 'timestamp' => time() - 100]);
        $store->store('new', ['raw' => 'y', 'timestamp' => time()]);

        $store->purgeOlderThan(50);
        expect(iterator_to_array($store->keys()))->toBe(['new']);
    });

    it('sanitizes keys to avoid directory traversal', function () {
        $store = storage();
        $store->store('../weird', ['raw' => 'z', 'timestamp' => 1]);

        $paths = glob($store->getBasePath().'/*.json');
        expect($paths)->toHaveCount(1)
            ->and(basename($paths[0]))->toBe('___weird.json');
    });
});
