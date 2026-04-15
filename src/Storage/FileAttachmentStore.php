<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore as AttachmentStoreContract;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\DTO\StoredAttachment;

use function array_filter;
use function array_values;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function rtrim;
use function unlink;

/**
 * Filesystem-only attachment store.
 *
 * Metadata is persisted as a per-message JSON sidecar at
 * `{base_path}/{message_id}.json`. Content bytes live on the shared
 * `mailbox.attachments.disk`, matching the database driver so the same
 * downloadable URLs work regardless of which MessageStore driver is
 * active.
 */
class FileAttachmentStore implements AttachmentStoreContract
{
    protected string $basePath;

    protected string $disk;

    protected string $contentBasePath;

    public function __construct(?string $basePath = null, ?string $disk = null, ?string $contentBasePath = null)
    {
        $this->basePath = $basePath ?: storage_path('app/mailbox/attachments-index');
        $this->disk = $disk ?: 'mailbox';
        $this->contentBasePath = $contentBasePath ?: 'attachments';

        if (! is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }
    }

    public function store(int|string $messageId, AttachmentData $attachment): StoredAttachment
    {
        $id = (string) Str::ulid();
        $extension = pathinfo($attachment->filename, PATHINFO_EXTENSION);
        $storedFilename = $id.($extension ? '.'.$extension : '');
        $path = $this->contentBasePath.'/'.$storedFilename;

        $content = $attachment->content;
        if ($this->isBase64($content)) {
            $content = base64_decode($content, true) ?: $content;
        }

        Storage::disk($this->disk)->put($path, $content);

        $record = new StoredAttachment(
            id: $id,
            messageId: $messageId,
            filename: $attachment->filename,
            mimeType: $attachment->mimeType,
            size: $attachment->size,
            disk: $this->disk,
            path: $path,
            cid: $attachment->cid,
            isInline: $attachment->isInline,
        );

        $this->appendToSidecar($messageId, $record);

        return $record;
    }

    public function find(string $id): ?StoredAttachment
    {
        foreach ($this->sidecarFiles() as $file) {
            foreach ($this->loadSidecar($file) as $record) {
                if ($record->id === $id) {
                    return $record;
                }
            }
        }

        return null;
    }

    public function findByMessage(int|string $messageId): array
    {
        return $this->loadSidecar($this->sidecarPath($messageId));
    }

    public function findByCid(int|string $messageId, string $cid): ?StoredAttachment
    {
        foreach ($this->findByMessage($messageId) as $record) {
            if ($record->cid === $cid) {
                return $record;
            }
        }

        return null;
    }

    public function delete(StoredAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        $records = $this->findByMessage($attachment->messageId);

        $remaining = array_values(array_filter(
            $records,
            static fn (StoredAttachment $r): bool => $r->id !== $attachment->id,
        ));

        $this->writeSidecar($attachment->messageId, $remaining);
    }

    public function deleteByMessage(int|string $messageId): void
    {
        $records = $this->findByMessage($messageId);

        foreach ($records as $record) {
            Storage::disk($record->disk)->delete($record->path);
        }

        $path = $this->sidecarPath($messageId);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function getContent(StoredAttachment $attachment): ?string
    {
        return Storage::disk($attachment->disk)->get($attachment->path);
    }

    public function clear(): void
    {
        foreach ($this->sidecarFiles() as $file) {
            @unlink($file);
        }

        Storage::disk($this->disk)->deleteDirectory($this->contentBasePath);
    }

    /**
     * @return list<string>
     */
    protected function sidecarFiles(): array
    {
        return glob(rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.json') ?: [];
    }

    protected function sidecarPath(int|string $messageId): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.((string) $messageId).'.json';
    }

    /**
     * @return array<int, StoredAttachment>
     */
    protected function loadSidecar(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        $records = [];

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $records[] = new StoredAttachment(
                id: (string) ($row['id'] ?? ''),
                messageId: $row['message_id'] ?? $row['messageId'] ?? '',
                filename: (string) ($row['filename'] ?? ''),
                mimeType: (string) ($row['mime_type'] ?? $row['mimeType'] ?? ''),
                size: (int) ($row['size'] ?? 0),
                disk: (string) ($row['disk'] ?? $this->disk),
                path: (string) ($row['path'] ?? ''),
                cid: isset($row['cid']) ? (string) $row['cid'] : null,
                isInline: (bool) ($row['is_inline'] ?? $row['isInline'] ?? false),
            );
        }

        return $records;
    }

    protected function appendToSidecar(int|string $messageId, StoredAttachment $record): void
    {
        $records = $this->findByMessage($messageId);
        $records[] = $record;

        $this->writeSidecar($messageId, $records);
    }

    /**
     * @param  array<int, StoredAttachment>  $records
     */
    protected function writeSidecar(int|string $messageId, array $records): void
    {
        $path = $this->sidecarPath($messageId);

        if ($records === []) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }

        $rows = array_map(
            static fn (StoredAttachment $r): array => [
                'id' => $r->id,
                'message_id' => $r->messageId,
                'filename' => $r->filename,
                'mime_type' => $r->mimeType,
                'size' => $r->size,
                'disk' => $r->disk,
                'path' => $r->path,
                'cid' => $r->cid,
                'is_inline' => $r->isInline,
            ],
            $records,
        );

        file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));
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
