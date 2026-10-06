<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Support;

use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;

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
     *
     * Pass the message's already-loaded attachments to resolve every cid
     * from that list instead of querying the store once per inline image.
     *
     * @param  array<int, StoredAttachment>|null  $attachments
     */
    public function rewrite(string $html, int|string $messageId, ?array $attachments = null): string
    {
        if (empty($html)) {
            return $html;
        }

        $pattern = '/(<img[^>]+src=["\'](cid:([^"\']+))["\'][^>]*>)/i';

        return preg_replace_callback($pattern, function ($matches) use ($messageId, $attachments) {
            $fullTag = $matches[1];
            $cidUrl = $matches[2]; // "cid:xyz"
            $cid = $matches[3];    // "xyz"

            $attachment = $attachments === null
                ? $this->attachmentStore->findByCid($messageId, $cid)
                : $this->findLoadedByCid($attachments, $cid);

            if ($attachment) {
                $inlineUrl = route('mailbox.attachments.inline', ['id' => $attachment->id]);

                return str_replace($cidUrl, $inlineUrl, $fullTag);
            }

            return $fullTag;
        }, $html) ?? $html;
    }

    /**
     * @param  array<int, StoredAttachment>  $attachments
     */
    private function findLoadedByCid(array $attachments, string $cid): ?StoredAttachment
    {
        foreach ($attachments as $attachment) {
            if ($attachment->cid === $cid) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Get all inline attachments (CID images) for a message.
     *
     * @return array<int, StoredAttachment>
     */
    public function getInlineAttachments(int|string $messageId): array
    {
        $attachments = $this->attachmentStore->findByMessage($messageId);

        return array_values(array_filter(
            $attachments,
            static fn (StoredAttachment $attachment): bool => $attachment->isInline,
        ));
    }
}
