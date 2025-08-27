<?php

use Redberry\MailboxForLaravel\Support\MessageNormalizer;

describe(MessageNormalizer::class, function () {
    it('normalizes a simple text-only email')->todo();
    it('normalizes an html-only email')->todo();
    it('normalizes a multipart/alternative email with both text and html')->todo();
    it('normalizes unicode headers and encoded words')->todo();
    it('normalizes multiple recipients in to/cc/bcc')->todo();
    it('extracts attachments with metadata and inline flags')->todo();
    it('preserves content-id mapping for inline images')->todo();
    it('parses Date header and falls back to current time if missing')->todo();
    it('handles empty subject and no sender gracefully')->todo();
    it('enforces a stable schema version and includes saved_at timestamp')->todo();
});
