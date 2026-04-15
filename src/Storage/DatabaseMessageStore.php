<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Models\MailboxMessage;

/**
 * Database-backed storage driver.
 *
 * Works nicely with SQLite/MySQL/Postgres as long as the mailbox_messages
 * table matches the payload shape (JSON columns where appropriate).
 */
class DatabaseMessageStore implements MessageStore
{
    public function store(array $payload): string
    {
        $id = $payload['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Payload is missing a canonical "id". CaptureService::store() must be called upstream.');
        }

        $payload['timestamp'] ??= time();
        $payload['saved_at'] ??= now()->toDateTimeString();

        MailboxMessage::query()->updateOrCreate(
            ['id' => $id],
            $payload,
        );

        return $id;
    }

    public function find(string $id): ?array
    {
        $record = MailboxMessage::query()->find($id);

        return $record?->toArray();
    }

    public function paginate(int $page, int $perPage, ?string $search = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return $this->applySearch(MailboxMessage::query(), $search)
            ->orderByDesc('timestamp')
            ->forPage($page, $perPage)
            ->get()
            ->toArray();
    }

    public function count(?string $search = null): int
    {
        return $this->applySearch(MailboxMessage::query(), $search)->count();
    }

    /**
     * Apply a case-insensitive LIKE search across subject, from, to, and
     * text body. JSON address columns are treated as strings — that matches
     * the serialized payload on all supported databases (SQLite / MySQL /
     * Postgres) without needing JSON-specific operators.
     *
     * @param  Builder<MailboxMessage>  $query
     * @return Builder<MailboxMessage>
     */
    protected function applySearch(Builder $query, ?string $search): Builder
    {
        $needle = $search !== null ? trim($search) : '';

        if ($needle === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('subject', 'like', $like)
                ->orWhere('from', 'like', $like)
                ->orWhere('to', 'like', $like)
                ->orWhere('text', 'like', $like);
        });
    }

    public function update(string $id, array $changes): ?array
    {
        $record = MailboxMessage::query()->find($id);

        if (! $record) {
            return null;
        }

        $record->fill($changes);
        $record->save();

        return $record->fresh()->toArray();
    }

    public function delete(string $id): void
    {
        MailboxMessage::query()
            ->whereKey($id)
            ->delete();
    }

    public function purgeOlderThan(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subSeconds($seconds)->getTimestamp();

        MailboxMessage::query()
            ->where('timestamp', '<', $cutoff)
            ->delete();
    }

    public function idsOlderThan(int $seconds): array
    {
        if ($seconds <= 0) {
            return [];
        }

        $cutoff = Carbon::now()->subSeconds($seconds)->getTimestamp();

        return MailboxMessage::query()
            ->where('timestamp', '<', $cutoff)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public function clear(): void
    {
        MailboxMessage::query()->delete();
    }
}
