<?php

namespace Redberry\MailboxForLaravel\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class InboxController
{
    public function __construct(
        protected CaptureService $captureService
    ) {}

    /**
     * Clear all messages from the inbox.
     */
    public function clear(): JsonResponse
    {
        $this->captureService->clearAll();

        return response()->json(['message' => 'Inbox cleared successfully']);
    }

    /**
     * Get inbox statistics.
     */
    public function stats(): JsonResponse
    {
        $messages = $this->captureService->all();
        $total = count($messages);
        $unread = 0;

        foreach ($messages as $message) {
            if (! $message['seen_at']) {
                $unread++;
            }
        }

        return response()->json([
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
        ]);
    }
}