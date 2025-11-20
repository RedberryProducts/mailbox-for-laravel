<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class DeleteMailboxMessageController
{
    public function __invoke(string $id, Request $request, CaptureService $service): \Illuminate\Http\RedirectResponse
    {
        $message = $service->find($id);

        if ($message === null) {
            return redirect()->back()->with([
                'flash' => [
                    'status' => 'error',
                    'title' => 'Message not found.',
                    'description' => 'The message you are trying to delete does not exist.',
                ],
            ]);
        }

        $service->delete($id);

        return redirect()->back()->with([
            'flash' => [
                'status' => 'success',
                'title' => 'Message deleted.',
                'description' => 'The message has been successfully deleted.',
            ],
        ]);
    }
}
