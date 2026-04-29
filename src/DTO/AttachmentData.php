<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

/**
 * Represents a single attachment extracted from an email.
 */
class AttachmentData
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
