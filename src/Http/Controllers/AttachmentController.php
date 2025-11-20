<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Response;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;
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
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="'.$attachment->filename.'"',
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
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.$attachment->filename.'"',
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
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'is_inline' => $attachment->is_inline,
                    'cid' => $attachment->cid,
                    'download_url' => route('mailbox.attachments.download', ['id' => $attachment->id]),
                    'inline_url' => route('mailbox.attachments.inline', ['id' => $attachment->id]),
                ],
                $attachments
            ),
        ];
    }
}
