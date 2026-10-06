<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

use InvalidArgumentException;

/**
 * Represents a single attachment extracted from an email.
 *
 * `$content` is always the raw attachment bytes. Use fromBase64() when you
 * hold base64-encoded content; stores never try to guess the encoding.
 */
class AttachmentData
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public int $size,
        public string $content,
        public ?string $cid = null,
        public bool $isInline = false,
    ) {}

    /**
     * Build from base64-encoded content, decoding it strictly.
     *
     * @throws InvalidArgumentException when $base64 is not valid base64.
     */
    public static function fromBase64(
        string $filename,
        string $mimeType,
        int $size,
        string $base64,
        ?string $cid = null,
        bool $isInline = false,
    ): self {
        $content = base64_decode($base64, true);

        if ($content === false) {
            throw new InvalidArgumentException("Attachment [{$filename}] content is not valid base64.");
        }

        return new self($filename, $mimeType, $size, $content, $cid, $isInline);
    }
}
