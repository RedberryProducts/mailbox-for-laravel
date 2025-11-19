<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Contracts;

/**
 * Storage abstraction for mailbox messages.
 *
 * Implementations are responsible for persisting a canonical payload array.
 * The payload MUST contain an "id" key that is unique per message.
 */
interface MessageStore
{
    /**
     * Persist an email payload and return its id.
     *
     * @param  array<string, mixed>  $payload  MUST include at least:
     *                                         - id: string
     *                                         - raw: string (optional but recommended)
     *                                         - timestamp: int (UNIX timestamp)
     */
    public function store(array $payload): string|int;

    /**
     * Retrieve a single payload by id.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * Retrieve a page of payloads.
     *
     * @param  int  $page  1-based page index
     * @param  int  $perPage  number of items per page
     * @return array<int, array<string, mixed>>
     */
    public function paginate(int $page, int $perPage): array;

    /**
     * Apply partial updates to a payload by id.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>|null The updated payload, or null if not found.
     */
    public function update(string $id, array $changes): ?array;

    /**
     * Delete a single payload by id.
     */
    public function delete(string $id): void;

    /**
     * Remove payloads older than $seconds (relative to now).
     *
     * @param  int  $seconds  Number of seconds to keep; older payloads will be purged.
     */
    public function purgeOlderThan(int $seconds): void;

    /**
     * Remove all stored payloads.
     */
    public function clear(): void;
}
