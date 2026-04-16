<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Search;

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

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';

        return $query->where(function (Builder $q) use ($like): void {
            foreach (self::SEARCHABLE_FIELDS as $field) {
                $q->orWhere($field, 'like', $like);
            }
        });
    }
}
