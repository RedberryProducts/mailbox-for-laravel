<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Support\CidRewriter;

class MailboxController
{
    public function __construct(
        protected AttachmentStore $attachmentStore,
        protected CidRewriter $cidRewriter
    ) {}

    public function __invoke(Request $request, CaptureService $service): View|JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) ($request->input('per_page') ?: config('mailbox.per_page', 20));
        $perPage = max(1, min($perPage, 100));
        $search = trim((string) $request->input('search', ''));
        $search = $search !== '' ? $search : null;

        $result = $service->list($page, $perPage, $search);

        $data = [
            'messages' => array_map(
                fn (MailboxMessageData $m) => $this->formatMessage($m),
                $result->data,
            ),
            'pagination' => [
                'total' => $result->total,
                'per_page' => $result->perPage,
                'current_page' => $result->currentPage,
                'has_more' => $result->hasMore,
                'latest_timestamp' => $result->latestTimestamp,
            ],
            'polling' => [
                'enabled' => config('mailbox.polling.enabled', true),
                'interval' => config('mailbox.polling.interval', 5000),
            ],
            'search' => $search ?? '',
            'mailboxPrefix' => config('mailbox.path', 'mailbox'),
            'csrfToken' => csrf_token(),
            'title' => 'Mailbox for Laravel',
            'subtitle' => 'Capture and view emails in your Laravel application',
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return ViewFactory::make('mailbox::app', ['data' => $data]);
    }

    /**
     * Format message with attachments and rewritten CID references.
     *
     * @return array<string, mixed>
     */
    protected function formatMessage(MailboxMessageData $message): array
    {
        $formatted = $message->toFrontendArray();

        $attachments = $this->attachmentStore->findByMessage($message->id);

        $formatted['attachments'] = array_map(
            static fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mimeType,
                'size' => $attachment->size,
                'is_inline' => $attachment->isInline,
                'download_url' => route('mailbox.attachments.download', ['id' => $attachment->id]),
                'inline_url' => route('mailbox.attachments.inline', ['id' => $attachment->id]),
            ],
            $attachments
        );

        if ($formatted['html_body']) {
            $formatted['html_body'] = $this->cidRewriter->rewrite(
                $formatted['html_body'],
                $message->id
            );
        }

        return $formatted;
    }
}
