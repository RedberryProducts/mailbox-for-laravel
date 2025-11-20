<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;

class DeleteMailboxMessageController
{
    public function __invoke(
        string $id,
        Request $request,
        CaptureService $service,
        AttachmentStore $attachmentStore
    ): \Illuminate\Http\RedirectResponse {
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

        // Delete attachments first
        $attachmentStore->deleteByMessage($id);

        // Then delete the message
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
