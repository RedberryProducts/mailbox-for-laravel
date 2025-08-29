<?php

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\StoreManager;

describe(StoreManager::class, function () {
    it('creates a file-based MessageStore when driver=file', function () {
        config(['mailbox-for-laravel.storage_driver' => 'file']);

        $store = (new StoreManager)->create();

        expect($store)->toBeInstanceOf(FileStorage::class);
    });

    it('throws when an unknown driver is configured', function () {
        config(['mailbox-for-laravel.storage_driver' => 'foo']);

        expect(fn () => (new StoreManager)->create())
            ->toThrow(InvalidArgumentException::class);
    });

    it('accepts a custom driver resolver via config', function () {
        $custom = new class implements MessageStore
        {
            public array $stored = [];

            public function store(string $key, array $value): void
            {
                $this->stored[$key] = $value;
            }

            public function retrieve(string $key): ?array
            {
                return $this->stored[$key] ?? null;
            }

            public function keys(?int $since = null): iterable
            {
                return array_keys($this->stored);
            }

            public function delete(string $key): void
            {
                unset($this->stored[$key]);
            }

            public function purgeOlderThan(int $seconds): void
            {
                $this->stored = [];
            }
        };

        config([
            'mailbox-for-laravel.storage_driver' => 'memory',
            'mailbox-for-laravel.storage_resolvers' => [
                'memory' => fn () => $custom,
            ],
        ]);

        $store = (new StoreManager)->create();
        expect($store)->toBe($custom);
    });

    it('passes configuration options to store implementations', function () {
        $tmp = sys_get_temp_dir().'/mailbox-tests';
        @mkdir($tmp, 0777, true);
        config([
            'mailbox-for-laravel.storage_driver' => 'file',
            'mailbox-for-laravel.storage' => ['path' => $tmp],
        ]);

        $store = (new StoreManager)->create();
        expect($store)->toBeInstanceOf(FileStorage::class)
            ->and($store->getBasePath())->toBe($tmp);
    });
});
