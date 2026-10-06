<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage\Concerns;

use Redberry\MailboxForLaravel\DTO\StoredAttachment;

/**
 * Fallback for AttachmentStore::findByMessages(): one findByMessage() call
 * per id. Custom drivers can use it to satisfy the contract and replace it
 * with a single batched lookup when their backend supports one.
 */
trait FindsAttachmentsByMessages
{
    /**
     * @param  array<int, int|string>  $messageIds
     * @return array<string, array<int, StoredAttachment>>
     */
    public function findByMessages(array $messageIds): array
    {
        $attachments = [];

        foreach ($messageIds as $messageId) {
            $attachments[(string) $messageId] = $this->findByMessage($messageId);
        }

        return $attachments;
    }
}
