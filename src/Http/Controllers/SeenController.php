<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Redberry\MailboxForLaravel\CaptureService;

class SeenController
{
    public function __invoke($id, CaptureService $service): Response
    {
        $service->update($id, ['seen_at' => now()]);

        return response()->noContent();
    }
}
