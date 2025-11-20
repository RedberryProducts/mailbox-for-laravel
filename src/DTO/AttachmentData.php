<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

use Spatie\LaravelData\Data;

/**
 * Represents a single attachment extracted from an email.
 */
class AttachmentData extends Data
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public int $size,
        public string $content, // base64-encoded or raw binary
        public ?string $cid = null,
        public bool $isInline = false,
    ) {}
}
