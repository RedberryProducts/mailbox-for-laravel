<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Redberry\MailboxForLaravel\CaptureService;

class MailboxController
{
    public function __invoke(Request $request, CaptureService $service): Response
    {
        $messages = $service->all(); // adapt to your API

        return Inertia::render('mailbox::Dashboard', [
            'messages' => array_values($messages['data'] ?? $messages), // support both shapes
            'title' => 'Mailbox for Laravel',
            'subtitle' => 'Local email capture and testing',
        ]);
    }
}
