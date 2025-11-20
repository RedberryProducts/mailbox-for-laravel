<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;
use Redberry\MailboxForLaravel\Support\CidRewriter;

class MailboxController
{
    public function __construct(
        protected AttachmentStore $attachmentStore,
        protected CidRewriter $cidRewriter
    ) {}

    public function __invoke(Request $request, CaptureService $service): Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) ($request->input('per_page') ?: config('mailbox.pagination.per_page'));

        $result = $service->list($page, $perPage);

        return Inertia::render('mailbox::Dashboard', [
            'messages' => array_map(
                fn (MailboxMessageData $m) => $this->formatMessage($m),
                $result['data'],
            ),
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $result['per_page'],
                'current_page' => $result['current_page'],
                'has_more' => $result['has_more'],
                'latest_timestamp' => $result['latest_timestamp'],
            ],
            'polling' => [
                'enabled' => config('mailbox.polling.enabled', true),
                'interval' => config('mailbox.polling.interval', 5000),
            ],
            'title' => 'Mailbox for Laravel',
            'subtitle' => 'Local email capture and testing',
        ]);
    }

    /**
     * Format message with attachments and rewritten CID references.
     *
     * @return array<string, mixed>
     */
    protected function formatMessage(MailboxMessageData $message): array
    {
        $formatted = $message->toFrontendArray();

        // Fetch attachments for this message
        $attachments = $this->attachmentStore->findByMessage($message->id);

        // Format attachments for frontend
        $formatted['attachments'] = array_map(
            static fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'is_inline' => $attachment->is_inline,
                'download_url' => route('mailbox.attachments.download', ['id' => $attachment->id]),
                'inline_url' => route('mailbox.attachments.inline', ['id' => $attachment->id]),
            ],
            $attachments
        );

        // Rewrite CID references in HTML body
        if ($formatted['html_body']) {
            $formatted['html_body'] = $this->cidRewriter->rewrite(
                $formatted['html_body'],
                $message->id
            );
        }

        return $formatted;
    }
}
