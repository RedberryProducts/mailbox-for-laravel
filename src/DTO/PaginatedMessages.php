<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

use Spatie\LaravelData\Data;

/**
 * Typed pagination result for mailbox messages.
 *
 * Returned by CaptureService::list(). Provides a single source of truth
 * for the pagination shape so controllers and tests never rely on loose
 * array keys.
 */
class PaginatedMessages extends Data
{
    /**
     * @param  array<int, MailboxMessageData>  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly bool $hasMore,
        public readonly ?int $latestTimestamp,
    ) {}
}
