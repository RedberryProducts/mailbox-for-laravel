<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class ClearMailboxController
{
    public function __invoke(Request $request, CaptureService $service): RedirectResponse
    {
        $service->clearAll();

        return redirect()->back()->with([
            'flash' => [
                'status' => 'success',
                'title' => 'Mailbox cleared.',
                'description' => 'All messages have been successfully deleted.',
            ],
        ]);
    }
}
