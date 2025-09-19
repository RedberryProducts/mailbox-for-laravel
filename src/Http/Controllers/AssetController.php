<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Redberry\MailboxForLaravel\CaptureService;

class AssetController
{
    public function __invoke(CaptureService $capture, string $message, string $asset)
    {
        $payload = $capture->get($message);

        abort_unless((bool) $payload, 404);

        $attachment = collect($payload['attachments'] ?? [])
            ->first(fn ($a) => ($a['filename'] ?? null) === $asset);
        abort_unless($attachment, 404);

        $headers = [
            'Content-Type' => $attachment['contentType'] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ];

        // decide disposition inline vs attachment
        if (($attachment['disposition'] ?? '') !== '') {
            $headers['Content-Disposition'] = $attachment['disposition'];
        }

        if (isset($attachment['path'])) {
            return Response::stream(function () use ($attachment) {
                $stream = fopen($attachment['path'], 'rb');
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, 200, $headers);
        }

        if (isset($attachment['content'])) {
            $content = base64_decode($attachment['content']);

            return new HttpResponse($content, 200, $headers);
        }

        abort(404);
    }
}
