<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class AssetController
{
    public function __invoke($path)
    {
        $full = __DIR__.'/../../../dist/'.$path;
        abort_unless(File::exists($full), 404);

        $mime = match (true) {
            Str::endsWith($path, '.js') => 'application/javascript',
            Str::endsWith($path, '.css') => 'text/css',
            Str::endsWith($path, '.map') => 'application/json',
            default => File::mimeType($full),
        };

        return Response::file($full, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

}
