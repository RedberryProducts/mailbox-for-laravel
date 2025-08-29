<?php

namespace Redberry\MailboxForLaravel;

use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageStore;

class CaptureService
{
    public function __construct(protected MessageStore $storage) {}

    /**
     * Persist the raw message and metadata.
     */
    public function store(array $payload): string
    {
        $raw = $payload['raw'] ?? '';
        $key = 'email_'.md5($raw).'_'.microtime(true);

        $this->storage->store($key, [
            'timestamp' => time(),
            ...$payload,
        ]);

        return $key;
    }

    /**
     * Retrieve a stored message by its key.
     */
    public function retrieve(string $key): ?array
    {
        return $this->storage->retrieve($key);
    }

    public function delete(string $key): void
    {
        $this->assertKey($key);
        $this->storage->delete($key);
    }

    public function all()
    {
        $messages = [];
        foreach ($this->storage->keys() as $key) {
            $messages[$key] = $this->retrieve($key);
        }

        return $messages;
    }

    public function get(string $key): ?array
    {
        $this->assertKey($key);

        return $this->retrieve($key);
    }

    public function list(int $page = 1, int $perPage = PHP_INT_MAX): array
    {
        $all = $this->all();
        if ($perPage === PHP_INT_MAX) {
            return $all;
        }

        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($all, $offset, $perPage, true);

        return [
            'data' => $slice,
            'total' => count($all),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function storeRaw(string $raw): string
    {
        return $this->store(['raw' => $raw]);
    }

    protected function assertKey(string $key): void
    {
        if (! preg_match('/^[A-Za-z0-9_.\-]+$/', $key)) {
            throw new InvalidArgumentException('Invalid id');
        }
    }
}
