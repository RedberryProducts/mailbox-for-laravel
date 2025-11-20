<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\CaptureService;

class DeleteMailboxMessageController
{
    public function __invoke(string $id, Request $request, CaptureService $service): JsonResponse
    {
        $message = $service->find($id);

        if ($message === null) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $service->delete($id);

        return response()->json(['status' => 'deleted']);
    }
}
