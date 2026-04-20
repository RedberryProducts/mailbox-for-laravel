<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class ClearMailboxController
{
    public function __invoke(
        Request $request,
        CaptureService $service,
    ): RedirectResponse|JsonResponse {
        $service->clearAll();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'title' => 'Mailbox cleared.',
                'description' => 'All messages have been successfully deleted.',
            ]);
        }

        return redirect()->back()->with([
            'flash' => [
                'status' => 'success',
                'title' => 'Mailbox cleared.',
                'description' => 'All messages have been successfully deleted.',
            ],
        ]);
    }
}
