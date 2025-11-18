<?php

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\StoreManager;

describe(StoreManager::class, function () {
    it('creates a file-based MessageStore when driver=file', function () {
        config(['mailbox.store.driver' => 'file']);

        $manager = app()->make(StoreManager::class);
        $store = $manager->driver();

        expect($store)->toBeInstanceOf(FileStorage::class);
    });

    it('throws when an unknown driver is configured', function () {
        config(['mailbox.store.driver' => 'foo']);

        $manager = app()->make(StoreManager::class);

        expect(fn () => $manager->driver())
            ->toThrow(InvalidArgumentException::class);
    });

    it('accepts a custom driver resolver via config', function () {
        $custom = new class implements MessageStore
        {
            public array $stored = [];

            public function store(array $payload): string|int
            {
                $id = $payload['id'] ?? 'msg_'.uniqid();
                $this->stored[$id] = $payload;

                return $id;
            }

            public function find(string $id): ?array
            {
                return $this->stored[$id] ?? null;
            }

            public function paginate(int $page, int $perPage): array
            {
                return array_values($this->stored);
            }

            public function delete(string $id): void
            {
                unset($this->stored[$id]);
            }

            public function update(string $id, array $changes): ?array
            {
                if (! isset($this->stored[$id])) {
                    return null;
                }
                $this->stored[$id] = array_merge($this->stored[$id], $changes);

                return $this->stored[$id];
            }

            public function purgeOlderThan(int $seconds): void
            {
                $this->stored = [];
            }

            public function clear(): void
            {
                $this->stored = [];
            }
        };

        config([
            'mailbox.store.driver' => 'memory',
            'mailbox.store.resolvers' => [
                'memory' => fn () => $custom,
            ],
        ]);

        $manager = app()->make(StoreManager::class);
        $store = $manager->driver();

        expect($store)->toBe($custom);
    });

    it('passes configuration options to store implementations', function () {
        $tmp = sys_get_temp_dir().'/mailbox-tests-'.uniqid();
        @mkdir($tmp, 0777, true);

        config([
            'mailbox.store.driver' => 'file',
            'mailbox.store.file' => ['path' => $tmp],
        ]);

        $manager = app()->make(StoreManager::class);
        $store = $manager->driver();

        expect($store)->toBeInstanceOf(FileStorage::class)
            ->and($store->getBasePath())->toBe($tmp);
    });
});
