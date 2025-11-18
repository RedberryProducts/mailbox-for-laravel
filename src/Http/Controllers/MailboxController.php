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
        $perPage = (int) $request->input('per_page', 10);

        $messages = $service->list($page, $perPage);

        return Inertia::render('mailbox::Dashboard', [
            'messages' => array_map(
                static fn (MailboxMessageData $m) => $m->toFrontendArray(),
                $messages,
            ),
            'title' => 'Mailbox for Laravel',
            'subtitle' => 'Local email capture and testing',
        ]);
    }
}
