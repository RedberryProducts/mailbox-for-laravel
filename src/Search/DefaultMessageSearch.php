<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Search;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Redberry\MailboxForLaravel\Contracts\MessageSearch;

use function array_map;
use function implode;
use function is_array;
use function json_encode;
use function mb_strtolower;
use function str_contains;
use function str_replace;
use function trim;

/**
 * Default search strategy that matches against subject, from, to, html and text content.
 *
 * This is the single canonical definition of which fields are searchable
 * and how matching works. Both in-memory and SQL paths use the same field
 * list, so switching storage drivers never changes search behavior.
 */
class DefaultMessageSearch implements MessageSearch
{
    /**
     * Canonical list of payload keys / database columns to search.
     *
     * @var list<string>
     */
    public const SEARCHABLE_FIELDS = ['subject', 'from', 'to', 'html', 'text'];

    public function matches(array $payload, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '') {
            return true;
        }

        $needleLower = mb_strtolower($needle);

        $haystack = implode(' ', array_map(
            static fn (string $field): string => is_array($payload[$field] ?? null)
                ? (string) json_encode($payload[$field])
                : (string) ($payload[$field] ?? ''),
            self::SEARCHABLE_FIELDS,
        ));

        return str_contains(mb_strtolower($haystack), $needleLower);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToQuery(Builder $query, string $needle): Builder
    {
        $needle = trim($needle);

        if ($needle === '') {
            return $query;
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $needle).'%';
        $connection = $query->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;
        $grammar = $query->getGrammar();

        return $query->where(function (Builder $q) use ($like, $driver, $grammar): void {
            foreach (self::SEARCHABLE_FIELDS as $field) {
                $q->orWhereRaw(self::likeClause($driver, $grammar->wrap($field)), [$like]);
            }
        });
    }

    /**
     * Build a case-insensitive, wildcard-safe LIKE clause for the driver.
     *
     * The needle is escaped with "!" above, and the clause declares it
     * explicitly: SQLite has no default escape character, and a backslash
     * would need driver-specific quoting inside the literal. Postgres LIKE is
     * case-sensitive and cannot be applied to json columns directly, so it
     * uses ILIKE against a text cast.
     */
    private static function likeClause(?string $driver, string $column): string
    {
        return match ($driver) {
            'pgsql' => "{$column}::text ilike ? escape '!'",
            default => "{$column} like ? escape '!'",
        };
    }
}
