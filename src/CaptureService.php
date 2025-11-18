<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel;

use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

/**
 * High-level mailbox API that the rest of the package uses.
 *
 * This service is storage-driver-agnostic and only talks to MessageStore.
 */
class CaptureService
{
    public function __construct(
        protected MessageStore $storage,
    ) {}

    /**
     * Persist the raw message payload and metadata.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): string|int
    {
        $timestamp = isset($payload['timestamp'])
            ? (int) $payload['timestamp']
            : time();

        $payload['timestamp'] ??= $timestamp;
        $payload['saved_at'] ??= now()->toIso8601String();

        return $this->storage->store($payload);
    }

    /**
     * Convenience method when you only have a raw message.
     */
    public function storeRaw(string $raw): string
    {
        return $this->store([
            'raw' => $raw,
            'timestamp' => time(),
        ]);
    }

    /**
     * Paginated list of messages as DTOs.
     *
     * @return array<int, MailboxMessageData>
     */
    public function list(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $items = $this->storage->paginate($page, $perPage);

        return array_map(
            static fn (array $data): MailboxMessageData => MailboxMessageData::from($data),
            $items,
        );
    }

    /**
     * Non-paginated list of all messages (dev-only, fine for small volumes).
     *
     * @return array<int, MailboxMessageData>
     */
    public function all(): array
    {
        $items = $this->storage->paginate(1, PHP_INT_MAX);

        return array_map(
            static fn (array $data): MailboxMessageData => MailboxMessageData::from($data),
            $items,
        );
    }

    public function find(string $id): ?MailboxMessageData
    {
        $data = $this->storage->find($id);

        return $data ? MailboxMessageData::from($data) : null;
    }

    /**
     * Apply partial updates (e.g. mark as seen).
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(string $id, array $changes): ?MailboxMessageData
    {
        $data = $this->storage->update($id, $changes);

        return $data ? MailboxMessageData::from($data) : null;
    }

    /**
     * Shortcut specifically for "seen" flag.
     */
    public function markSeen(string $id): ?MailboxMessageData
    {
        return $this->update($id, ['seen_at' => now()->toIso8601String()]);
    }

    public function delete(string $id): void
    {
        $this->storage->delete($id);
    }

    public function purgeOlderThan(int $seconds): void
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Seconds must be greater than zero.');
        }

        $this->storage->purgeOlderThan($seconds);
    }

    public function clearAll(): void
    {
        $this->storage->clear();
    }
}
