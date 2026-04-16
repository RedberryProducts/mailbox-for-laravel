<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Strategy for searching mailbox messages.
 *
 * Two shapes cover all driver types:
 * - `matches()` for in-memory drivers (file, custom)
 * - `applyToQuery()` for SQL-backed drivers (database)
 *
 * Both shapes must search the same fields with equivalent semantics
 * so that switching drivers never changes search behavior.
 */
interface MessageSearch
{
    /**
     * Does the payload match the given search needle?
     *
     * Used by in-memory drivers (e.g. FileStorage) to filter payloads
     * after loading them from disk.
     *
     * @param  array<string, mixed>  $payload  The canonical message payload.
     * @param  string  $needle  Raw search input (may contain whitespace).
     */
    public function matches(array $payload, string $needle): bool;

    /**
     * Apply search constraints to an Eloquent query builder.
     *
     * Used by SQL-backed drivers (e.g. DatabaseMessageStore) to push
     * filtering down to the database.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $needle  Raw search input (may contain whitespace).
     * @return Builder<TModel>
     */
    public function applyToQuery(Builder $query, string $needle): Builder;
}
