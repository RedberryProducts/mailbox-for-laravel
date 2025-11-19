<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

class MailboxController
{
    public function __invoke(Request $request, CaptureService $service): Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) ($request->input('per_page') ?: config('mailbox.pagination.per_page'));

        $result = $service->list($page, $perPage);

        return Inertia::render('mailbox::Dashboard', [
            'messages' => array_map(
                static fn (MailboxMessageData $m) => $m->toFrontendArray(),
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
}
