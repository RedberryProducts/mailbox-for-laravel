<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Redberry\MailboxForLaravel\CaptureService;

class SeenController
{
    public function __invoke(string $id, CaptureService $service): JsonResponse
    {
        $message = $service->retrieve($id);

        if (! $message) {
            return response()->json([
                'message' => 'Message not found.',
            ], 404);
        }

        // Only update if not already seen (idempotent)
        if (! $message->seen_at) {
            $message = $service->update($id, ['seen_at' => now()->toIso8601String()]);
        }

        return response()->json([
            'id' => $message->id,
            'seen_at' => $message->seen_at,
        ]);
    }
}
