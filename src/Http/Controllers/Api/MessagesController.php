<?php

namespace Redberry\MailboxForLaravel\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class MessagesController
{
    public function __construct(
        protected CaptureService $captureService
    ) {}

    /**
     * Get paginated list of messages.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));

        $result = $this->captureService->list($page, $perPage);

        return response()->json([
            'data' => array_values($result['data'] ?? $result),
            'total' => $result['total'] ?? count($result),
            'page' => $result['page'] ?? $page,
            'per_page' => $result['per_page'] ?? $perPage,
            'last_page' => (int) ceil(($result['total'] ?? count($result)) / $perPage),
        ]);
    }

    /**
     * Get a specific message by ID.
     */
    public function show(string $id): JsonResponse
    {
        $message = $this->captureService->get($id);

        if (! $message) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        return response()->json($message);
    }

    /**
     * Mark a message as seen.
     */
    public function markAsSeen(string $id): JsonResponse
    {
        $message = $this->captureService->update($id, ['seen_at' => now()]);

        if (! $message) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        return response()->json($message);
    }

    /**
     * Delete a specific message.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->captureService->delete($id);
            return response()->json(['message' => 'Message deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'Invalid message ID'], 400);
        }
    }
}