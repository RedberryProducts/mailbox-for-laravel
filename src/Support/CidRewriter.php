<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Support;

use Redberry\MailboxForLaravel\Models\MailboxAttachment;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;

/**
 * Rewrites CID references in HTML to inline attachment URLs.
 */
class CidRewriter
{
    public function __construct(
        protected AttachmentStore $attachmentStore
    ) {}

    /**
     * Replace cid: references in HTML with actual inline preview URLs.
     */
    public function rewrite(string $html, int|string $messageId): string
    {
        if (empty($html)) {
            return $html;
        }

        // Find all cid: references in src attributes
        $pattern = '/(<img[^>]+src=["\'](cid:([^"\']+))["\'][^>]*>)/i';

        return preg_replace_callback($pattern, function ($matches) use ($messageId) {
            $fullTag = $matches[1];
            $cidUrl = $matches[2]; // "cid:xyz"
            $cid = $matches[3];    // "xyz"

            // Find attachment by CID
            $attachment = $this->attachmentStore->findByCid($messageId, $cid);

            if ($attachment) {
                $inlineUrl = route('mailbox.attachments.inline', ['id' => $attachment->id]);

                return str_replace($cidUrl, $inlineUrl, $fullTag);
            }

            // If no attachment found, leave as is
            return $fullTag;
        }, $html) ?? $html;
    }

    /**
     * Get all inline attachments (CID images) for a message.
     *
     * @return array<int, MailboxAttachment>
     */
    public function getInlineAttachments(int|string $messageId): array
    {
        $attachments = $this->attachmentStore->findByMessage($messageId);

        return array_filter($attachments, static fn ($attachment) => $attachment->is_inline);
    }
}
