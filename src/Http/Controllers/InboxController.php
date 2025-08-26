<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Redberry\MailboxForLaravel\CaptureService;

class InboxController
{
    public function __invoke(Request $request, CaptureService $service): View|JsonResponse
    {
        $messages = $service->all(); // adapt to your API

        $payload = [
            'messages' => array_values($messages['data'] ?? $messages), // support both shapes
        ];

        // If the client asks for JSON, return JSON (Vue will update without reload)
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        // Otherwise, render the Blade view and hydrate initial props
        return view('inbox::index', ['data' => $payload]);
    }
}
