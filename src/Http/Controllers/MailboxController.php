<?php

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
        $perPage = (int) $request->input('per_page', 20);

        $result = $service->list($page, $perPage);

        /** @var MailboxMessageData[] $messages */
        $messages = $result['data'];

        return Inertia::render('mailbox::Dashboard', [
            'messages' => array_map(
                fn(MailboxMessageData $m) => $m->toFrontendArray(),
                $messages,
            ),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
            'title' => 'Mailbox for Laravel',
            'subtitle' => 'Local email capture and testing',
        ]);
    }
}
