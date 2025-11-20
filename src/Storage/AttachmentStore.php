<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\Models\MailboxAttachment;

/**
 * Handles attachment metadata and file storage.
 */
class AttachmentStore
{
    /**
     * Save attachment metadata to DB and file content to disk.
     */
    public function store(int|string $messageId, AttachmentData $attachment): MailboxAttachment
    {
        $disk = config('mailbox.attachments.disk', 'mailbox');
        $basePath = config('mailbox.attachments.path', 'attachments');

        // Generate unique filename to avoid collisions
        $id = (string) Str::ulid();
        $extension = pathinfo($attachment->filename, PATHINFO_EXTENSION);
        $storedFilename = $id.($extension ? '.'.$extension : '');
        $path = $basePath.'/'.$storedFilename;

        // Decode base64 content if needed
        $content = $attachment->content;
        if ($this->isBase64($content)) {
            $content = base64_decode($content, true) ?: $content;
        }

        // Store file to disk
        Storage::disk($disk)->put($path, $content);

        // Store metadata in DB
        return MailboxAttachment::query()->create([
            'id' => $id,
            'message_id' => $messageId,
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mimeType,
            'size' => $attachment->size,
            'disk' => $disk,
            'path' => $path,
            'cid' => $attachment->cid,
            'is_inline' => $attachment->isInline,
        ]);
    }

    /**
     * Find attachment by ID.
     */
    public function find(string $id): ?MailboxAttachment
    {
        return MailboxAttachment::query()->find($id);
    }

    /**
     * Find all attachments for a message.
     *
     * @return array<int, MailboxAttachment>
     */
    public function findByMessage(int|string $messageId): array
    {
        return MailboxAttachment::query()
            ->where('message_id', $messageId)
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /**
     * Find attachment by Content-ID (for inline images).
     */
    public function findByCid(int|string $messageId, string $cid): ?MailboxAttachment
    {
        return MailboxAttachment::query()
            ->where('message_id', $messageId)
            ->where('cid', $cid)
            ->first();
    }

    /**
     * Delete all attachments for a specific message.
     */
    public function deleteByMessage(int|string $messageId): void
    {
        $attachments = $this->findByMessage($messageId);

        foreach ($attachments as $attachment) {
            $this->delete($attachment);
        }
    }

    /**
     * Delete a single attachment (metadata + file).
     */
    public function delete(MailboxAttachment $attachment): void
    {
        // Delete file from disk
        Storage::disk($attachment->disk)->delete($attachment->path);

        // Delete metadata from DB
        $attachment->delete();
    }

    /**
     * Delete all attachments (for clearAll operation).
     */
    public function deleteAll(): void
    {
        $disk = config('mailbox.attachments.disk', 'mailbox');
        $basePath = config('mailbox.attachments.path', 'attachments');

        // Delete all files from disk
        Storage::disk($disk)->deleteDirectory($basePath);

        // Delete all metadata from DB
        MailboxAttachment::query()->delete();
    }

    /**
     * Get file content for an attachment.
     */
    public function getContent(MailboxAttachment $attachment): ?string
    {
        return Storage::disk($attachment->disk)->get($attachment->path);
    }

    /**
     * Check if a string is base64 encoded.
     */
    private function isBase64(string $string): bool
    {
        // Quick check: base64 strings are usually longer and contain specific chars
        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string) && strlen($string) % 4 === 0) {
            $decoded = base64_decode($string, true);

            return $decoded !== false && base64_encode($decoded) === $string;
        }

        return false;
    }
}
