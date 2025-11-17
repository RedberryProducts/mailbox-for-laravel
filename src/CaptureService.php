<?php

namespace Redberry\MailboxForLaravel;

use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

class CaptureService
{
    public function __construct(protected MessageStore $storage) {}

    /**
     * Persist the raw message and metadata.
     *
     * @param  array  $payload  - raw email payload (text, html, headers, etc.)
     */
    public function store(array $payload): string
    {
        $raw = $payload['raw'] ?? '';
        $id = 'email_'.md5($raw).'_'.microtime(true);

        // Build the canonical DTO
        $message = MailboxMessageData::from([
            'timestamp' => time(),
            'id' => $id,
            'seen_at' => null,
            'version' => $payload['version'] ?? 1,
            ...$payload,
        ]);

        // Persist as array (storage stays dumb)
        $this->storage->store($id, $message->toArray());

        return $id;
    }

    public function update(string $key, array $values): ?MailboxMessageData
    {
        $this->assertKey($key);

        $stored = $this->storage->update($key, $values);

        return $stored ? MailboxMessageData::from($stored) : null;
    }

    public function retrieve(string $key): ?MailboxMessageData
    {
        $this->assertKey($key);

        $stored = $this->storage->retrieve($key);

        return $stored ? MailboxMessageData::from($stored) : null;
    }

    public function delete(string $key): void
    {
        $this->assertKey($key);
        $this->storage->delete($key);
    }

    /**
     * @return MailboxMessageData[]
     */
    public function all(): array
    {
        $messages = [];

        foreach ($this->storage->keys() as $key) {
            $raw = $this->storage->retrieve($key);

            if (! $raw) {
                continue;
            }

            $messages[$key] = MailboxMessageData::from($raw);
        }

        // Sort by timestamp (newest first)
        uasort($messages, fn (MailboxMessageData $a, MailboxMessageData $b) => $b->timestamp <=> $a->timestamp);

        return $messages;
    }

    public function get(string $key): ?MailboxMessageData
    {
        $this->assertKey($key);

        $raw = $this->storage->retrieve($key);

        return $raw ? MailboxMessageData::from($raw) : null;
    }

    /**
     * Paginated list that still returns DTOs.
     *
     * @return array{data: MailboxMessageData[], total:int, page:int, per_page:int}
     */
    public function list(int $page = 1, int $perPage = PHP_INT_MAX): array
    {
        $all = $this->all();

        if ($perPage === PHP_INT_MAX) {
            return [
                'data' => array_values($all),
                'total' => count($all),
                'page' => 1,
                'per_page' => count($all),
            ];
        }

        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($all, $offset, $perPage);

        return [
            'data' => array_values($slice),
            'total' => count($all),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function clearAll(): bool
    {
        return $this->storage->clear();
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
