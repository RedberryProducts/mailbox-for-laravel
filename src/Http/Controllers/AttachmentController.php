<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController
{
    public function __construct(
        protected AttachmentStore $attachmentStore
    ) {}

    /**
     * Download attachment.
     */
    public function download(string $id): Response|StreamedResponse
    {
        $attachment = $this->attachmentStore->find($id);

        if (! $attachment) {
            abort(404, 'Attachment not found');
        }

        $content = $this->attachmentStore->getContent($attachment);

        if ($content === null) {
            abort(404, 'Attachment file not found');
        }

        return response($content, 200, [
            'Content-Type' => $attachment->mimeType,
            'Content-Disposition' => $this->contentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $attachment->filename),
            'Content-Length' => (string) $attachment->size,
        ]);
    }

    /**
     * View/preview attachment inline.
     */
    public function inline(string $id): Response|StreamedResponse
    {
        $attachment = $this->attachmentStore->find($id);

        if (! $attachment) {
            abort(404, 'Attachment not found');
        }

        $content = $this->attachmentStore->getContent($attachment);

        if ($content === null) {
            abort(404, 'Attachment file not found');
        }

        return response($content, 200, [
            'Content-Type' => $attachment->mimeType,
            'Content-Disposition' => $this->contentDisposition(HeaderUtils::DISPOSITION_INLINE, $attachment->filename),
            'Content-Length' => (string) $attachment->size,
        ]);
    }

    /**
     * List attachments for a message.
     *
     * @return array<string, mixed>
     */
    public function list(string $messageId): array
    {
        $attachments = $this->attachmentStore->findByMessage($messageId);

        return [
            'attachments' => array_map(
                static fn ($attachment) => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mimeType,
                    'size' => $attachment->size,
                    'is_inline' => $attachment->isInline,
                    'cid' => $attachment->cid,
                    'download_url' => route('mailbox.attachments.download', ['id' => $attachment->id]),
                    'inline_url' => route('mailbox.attachments.inline', ['id' => $attachment->id]),
                ],
                $attachments
            ),
        ];
    }

    /**
     * Build a Content-Disposition header that survives any captured filename.
     *
     * Control characters are stripped and path separators replaced (Symfony rejects
     * them), the original name is sent as RFC 5987 filename*, and an ASCII
     * fallback is provided for clients that only read filename.
     */
    protected function contentDisposition(string $disposition, string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(['/', '\\'], '_', $filename)));

        if ($filename === '') {
            $filename = 'attachment';
        }

        $fallback = (string) preg_replace('/[^\x20-\x7E]|%/', '_', Str::ascii($filename));

        return HeaderUtils::makeDisposition($disposition, $filename, $fallback);
    }
}
