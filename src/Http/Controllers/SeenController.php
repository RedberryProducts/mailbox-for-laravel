<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

class SeenController
{
    public function __invoke(string $id, CaptureService $service): JsonResponse
    {
        /** @var MailboxMessageData|null $message */
        $message = $service->find($id);

        if (! $message) {
            return response()->json([
                'message' => 'Message not found.',
            ], 404);
        }

        // Idempotent: if already seen, don't touch it
        if (! $message->seen_at) {
            $updated = $service->markSeen($id);

            // In case something went wrong in storage, fall back to original
            if ($updated) {
                $message = $updated;
            }
        }

        return response()->json([
            'id' => $message->id,
            'seen_at' => $message->seen_at,
        ]);
    }
}
