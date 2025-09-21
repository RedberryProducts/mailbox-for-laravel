<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Redberry\MailboxForLaravel\CaptureService;

class InboxController
{
    public function __invoke(Request $request, CaptureService $service): \Illuminate\Contracts\View\View|JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = $request->wantsJson() ? min(100, max(1, (int) $request->get('per_page', 50))) : PHP_INT_MAX;

        $messages = $perPage === PHP_INT_MAX ? $service->all() : $service->list($page, $perPage);

        // Return JSON for API requests
        if ($request->wantsJson()) {
            return response()->json([
                'data' => array_values($messages['data'] ?? $messages),
                'total' => $messages['total'] ?? count($messages),
                'page' => $messages['page'] ?? $page,
                'per_page' => $messages['per_page'] ?? $perPage,
                'last_page' => (int) ceil(($messages['total'] ?? count($messages)) / $perPage),
            ]);
        }

        $payload = [
            'messages' => array_values($messages['data'] ?? $messages), // support both shapes
        ];

        // Otherwise, render the Blade view and hydrate initial props
        return View::make('inbox::index', ['data' => $payload]);
    }
}
