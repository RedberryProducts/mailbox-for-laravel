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
     * Retrieve a page of payloads, optionally filtered by a free-text search.
     *
     * @param  int  $page  1-based page index
     * @param  int  $perPage  number of items per page
     * @param  string|null  $search  Case-insensitive needle matched against
     *                               subject, from, to, and text body. Null
     *                               or empty returns all messages.
     * @return array<int, array<string, mixed>>
     */
    public function paginate(int $page, int $perPage, ?string $search = null): array;

    /**
     * Get the total count of stored messages, optionally matching a search.
     */
    public function count(?string $search = null): int;

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
     * List ids of payloads older than $seconds (relative to now).
     *
     * Used by CaptureService to cascade attachment cleanup before the
     * messages themselves are purged. Returning ids (instead of relying
     * on a callback inside `purgeOlderThan`) keeps drivers stateless and
     * easy to test.
     *
     * @return array<int, string>
     */
    public function idsOlderThan(int $seconds): array;

    /**
     * Remove all stored payloads.
     */
    public function clear(): void;
}
