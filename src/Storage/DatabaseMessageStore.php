<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use Carbon\Carbon;
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
    public function store(array $payload): string|int
    {
        $id = $payload['id'] ?? null;

        $payload['timestamp'] ??= time();
        $payload['saved_at'] ??= now()->toDateTimeString();

        $message = MailboxMessage::query()->updateOrCreate(
            ['id' => $id],
            $payload,
        );

        return $id ?? $message->id;
    }

    public function find(string $id): ?array
    {
        $record = MailboxMessage::query()->find($id);

        return $record?->toArray();
    }

    public function paginate(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return MailboxMessage::query()
            ->orderByDesc('timestamp')
            ->forPage($page, $perPage)
            ->get()
            ->toArray();
    }

    public function update(string $id, array $changes): ?array
    {
        $record = MailboxMessage::query()->find($id);

        if (!$record) {
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

    public function clear(): void
    {
        MailboxMessage::query()->delete();
    }
}
