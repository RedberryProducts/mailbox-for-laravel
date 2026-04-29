<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

/**
 * Driver-agnostic attachment record returned by any AttachmentStore.
 *
 * Fields mirror the shape originally exposed by the Eloquent
 * MailboxAttachment model so consumers (controllers, CidRewriter,
 * assertions) only ever depend on this contract — not on a specific
 * storage backend.
 */
class StoredAttachment
{
    public function __construct(
        public string $id,
        public int|string $messageId,
        public string $filename,
        public string $mimeType,
        public int $size,
        public string $disk,
        public string $path,
        public ?string $cid,
        public bool $isInline,
    ) {}
}
