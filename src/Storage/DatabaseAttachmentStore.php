<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore as AttachmentStoreContract;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;
use Redberry\MailboxForLaravel\Models\MailboxAttachment;

/**
 * Database-backed attachment store.
 *
 * Metadata lives in the `mailbox_attachments` table (via the
 * MailboxAttachment Eloquent model, which benefits from the cascade
 * foreign key on `mailbox_messages`). File content lives on the
 * configured `mailbox.attachments.disk`.
 */
class DatabaseAttachmentStore implements AttachmentStoreContract
{
    public function store(int|string $messageId, AttachmentData $attachment): StoredAttachment
    {
        $disk = (string) config('mailbox.attachments.disk', 'mailbox');
        $basePath = (string) config('mailbox.attachments.path', 'attachments');

        $id = (string) Str::ulid();
        $extension = pathinfo($attachment->filename, PATHINFO_EXTENSION);
        $storedFilename = $id.($extension ? '.'.$extension : '');
        $path = $basePath.'/'.$storedFilename;

        $content = $attachment->content;
        if ($this->isBase64($content)) {
            $content = base64_decode($content, true) ?: $content;
        }

        Storage::disk($disk)->put($path, $content);

        $record = MailboxAttachment::query()->create([
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

        return $this->toDto($record);
    }

    public function find(string $id): ?StoredAttachment
    {
        $record = MailboxAttachment::query()->find($id);

        return $record ? $this->toDto($record) : null;
    }

    public function findByMessage(int|string $messageId): array
    {
        return MailboxAttachment::query()
            ->where('message_id', $messageId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (MailboxAttachment $record): StoredAttachment => $this->toDto($record))
            ->values()
            ->all();
    }

    public function findByCid(int|string $messageId, string $cid): ?StoredAttachment
    {
        $record = MailboxAttachment::query()
            ->where('message_id', $messageId)
            ->where('cid', $cid)
            ->first();

        return $record ? $this->toDto($record) : null;
    }

    public function delete(StoredAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        MailboxAttachment::query()->whereKey($attachment->id)->delete();
    }

    public function deleteByMessage(int|string $messageId): void
    {
        foreach ($this->findByMessage($messageId) as $attachment) {
            $this->delete($attachment);
        }
    }

    public function getContent(StoredAttachment $attachment): ?string
    {
        return Storage::disk($attachment->disk)->get($attachment->path);
    }

    public function clear(): void
    {
        $disk = (string) config('mailbox.attachments.disk', 'mailbox');
        $basePath = (string) config('mailbox.attachments.path', 'attachments');

        Storage::disk($disk)->deleteDirectory($basePath);

        MailboxAttachment::query()->delete();
    }

    protected function toDto(MailboxAttachment $record): StoredAttachment
    {
        return new StoredAttachment(
            id: (string) $record->id,
            messageId: $record->message_id,
            filename: (string) $record->filename,
            mimeType: (string) $record->mime_type,
            size: (int) $record->size,
            disk: (string) $record->disk,
            path: (string) $record->path,
            cid: $record->cid,
            isInline: (bool) $record->is_inline,
        );
    }

    private function isBase64(string $string): bool
    {
        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string) && strlen($string) % 4 === 0) {
            $decoded = base64_decode($string, true);

            return $decoded !== false && base64_encode($decoded) === $string;
        }

        return false;
    }
}
