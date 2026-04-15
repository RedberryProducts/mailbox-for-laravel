<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Contracts;

use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;

/**
 * Driver-agnostic persistence layer for email attachments.
 *
 * Implementations decide where metadata and content live (database +
 * filesystem, filesystem-only, remote object storage, …) but always
 * return a StoredAttachment DTO so callers never couple to a backend.
 */
interface AttachmentStore
{
    /**
     * Persist an attachment for the given message and return its record.
     */
    public function store(int|string $messageId, AttachmentData $attachment): StoredAttachment;

    /**
     * Retrieve a single attachment by its stable id.
     */
    public function find(string $id): ?StoredAttachment;

    /**
     * Retrieve every attachment belonging to a message, ordered oldest-first.
     *
     * @return array<int, StoredAttachment>
     */
    public function findByMessage(int|string $messageId): array;

    /**
     * Find an inline attachment by its Content-ID within a message.
     */
    public function findByCid(int|string $messageId, string $cid): ?StoredAttachment;

    /**
     * Delete a single attachment (metadata + content).
     */
    public function delete(StoredAttachment $attachment): void;

    /**
     * Delete every attachment belonging to a message.
     */
    public function deleteByMessage(int|string $messageId): void;

    /**
     * Read the raw content bytes for an attachment, or null if missing.
     */
    public function getContent(StoredAttachment $attachment): ?string;

    /**
     * Remove every attachment the store is responsible for.
     */
    public function clear(): void;
}
