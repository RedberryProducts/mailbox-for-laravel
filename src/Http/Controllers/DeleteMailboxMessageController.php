<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class DeleteMailboxMessageController
{
    public function __invoke(
        string $id,
        Request $request,
        CaptureService $service,
    ): RedirectResponse|JsonResponse {
        $message = $service->find($id);

        if ($message === null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'title' => 'Message not found.',
                    'description' => 'The message you are trying to delete does not exist.',
                ], 404);
            }

            return redirect()->back()->with([
                'flash' => [
                    'status' => 'error',
                    'title' => 'Message not found.',
                    'description' => 'The message you are trying to delete does not exist.',
                ],
            ]);
        }

        $service->delete($id);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'title' => 'Message deleted.',
                'description' => 'The message has been successfully deleted.',
            ]);
        }

        return redirect()->back()->with([
            'flash' => [
                'status' => 'success',
                'title' => 'Message deleted.',
                'description' => 'The message has been successfully deleted.',
            ],
        ]);
    }
}
