<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Redberry\MailboxForLaravel\CaptureService;

class ClearMailboxController
{
    public function __invoke(Request $request, CaptureService $service): View|JsonResponse
    {
        $service->clearAll();

        return response()->json();
    }
}
