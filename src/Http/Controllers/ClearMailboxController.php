<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;

class ClearMailboxController
{
    public function __invoke(
        Request $request,
        CaptureService $service,
        AttachmentStore $attachmentStore
    ): RedirectResponse {
        // Clear all attachments first
        $attachmentStore->deleteAll();

        // Then clear all messages
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
