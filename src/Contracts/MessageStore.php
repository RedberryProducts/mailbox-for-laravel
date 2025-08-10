<?php

namespace Redberry\MailboxForLaravel\Contracts;

interface MessageStore
{
    /**
     * Persist an email payload.
     *
     * @param  string  $key  Unique key (you generate it).
     * @param  array  $value  Associative array payload; MUST include at least: ['raw' => string, 'timestamp' => int]
     */
    public function store(string $key, array $value): void;

    /**
     * Retrieve a single payload by key.
     *
     * @param  string  $key  Unique key to retrieve the payload.
     * @return array|null Returns the payload array or null if not found.
     */
    public function retrieve(string $key): ?array;

    /**
     * Retrieve multiple payload keys optionally filtered by a UNIX timestamp (>= since).
     *
     *
     * @return iterable<string> Keys
     */
    public function keys(?int $since = null): iterable;

    /**
     * Delete a payload by key.
     *
     * @param  string  $key  Unique key to delete the payload.
     */
    public function delete(string $key): void;

    /**
     * Remove payloads older than $seconds (relative to now).
     *
     * @param  int  $seconds  Number of seconds to keep; older payloads will be purged.
     */
    public function purgeOlderThan(int $seconds): void;
}
