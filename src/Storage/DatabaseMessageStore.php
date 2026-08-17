<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageSearch;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Search\DefaultMessageSearch;

/**
 * Database-backed storage driver.
 *
 * Works nicely with SQLite/MySQL/Postgres as long as the mailbox_messages
 * table matches the payload shape (JSON columns where appropriate).
 */
class DatabaseMessageStore implements MessageStore
{
    /**
     * Payload keys this driver can persist, i.e. the writable columns on the
     * messages table. The payload contract is an open map — the file driver
     * keeps it verbatim as JSON — but here it has to land on a fixed schema,
     * so anything outside this list is dropped instead of being handed to the
     * query builder as an unknown column.
     *
     * `id` is absent on purpose: it is passed as the match attribute.
     */
    private const COLUMNS = [
        'timestamp',
        'seen_at',
        'version',
        'saved_at',
        'message_id',
        'subject',
        'date',
        'from',
        'sender',
        'to',
        'cc',
        'bcc',
        'reply_to',
        'text',
        'html',
        'headers',
        'attachments',
        'raw',
    ];

    public function __construct(
        private readonly MessageSearch $search = new DefaultMessageSearch,
    ) {}

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
            array_intersect_key($payload, array_flip(self::COLUMNS)),
        );

        return $id;
    }

    public function find(string $id): ?array
    {
        $record = MailboxMessage::query()->find($id);

        return $record?->toArray();
    }

    public function findIdByMessageId(string $messageId): ?string
    {
        $record = MailboxMessage::query()
            ->where('message_id', $messageId)
            ->first(['id']);

        return $record?->id;
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
     * @param  Builder<MailboxMessage>  $query
     * @return Builder<MailboxMessage>
     */
    protected function applySearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        return $this->search->applyToQuery($query, $search);
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
