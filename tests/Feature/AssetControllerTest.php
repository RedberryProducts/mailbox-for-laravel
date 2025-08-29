<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Http\Controllers\AssetController;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeInboxMiddleware;

describe(AssetController::class, function () {
    beforeEach(function () {
        Route::middleware(AuthorizeInboxMiddleware::class)->group(function () {
            Route::get('/mailbox/messages/{message}/attachments/{asset}', AssetController::class);
        });
        config()->set('inbox.public', true);
    });

    function storeMessage(): array {
        $svc = app(CaptureService::class);
        $payload = [
            'attachments' => [[
                'filename' => 'file.txt',
                'contentType' => 'text/plain',
                'disposition' => 'inline; filename="file.txt"',
                'content' => base64_encode('hello'),
            ]],
        ];
        $key = $svc->store($payload);
        return [$svc, $key];
    }

    it('serves an inline asset with correct content-type and cache headers', function () {
        [$svc, $key] = storeMessage();

        $response = $this->get("/mailbox/messages/{$key}/attachments/file.txt");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        expect($response->getContent())->toBe('hello');
    });

    it('returns 404 for non-existing message', function () {
        $this->get('/mailbox/messages/missing/attachments/file.txt')->assertNotFound();
    });

    it('returns 404 for non-existing asset in existing message', function () {
        [$svc, $key] = storeMessage();
        $this->get("/mailbox/messages/{$key}/attachments/missing.txt")->assertNotFound();
    });

    it('rejects unauthorized access when middleware denies', function () {
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);
        config()->set('inbox.public', false);

        $this->get('/mailbox/messages/abc/attachments/file.txt')->assertForbidden();
    });

    it('streams large assets without loading entire file into memory', function () {
        $file = tempnam(sys_get_temp_dir(), 'inbox-');
        file_put_contents($file, str_repeat('A', 1024 * 1024));

        $svc = app(CaptureService::class);
        $key = $svc->store([
            'attachments' => [[
                'filename' => 'big.txt',
                'contentType' => 'text/plain',
                'path' => $file,
                'disposition' => 'attachment; filename="big.txt"',
            ]],
        ]);

        $response = $this->get("/mailbox/messages/{$key}/attachments/big.txt");
        $response->assertOk();
        expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
    });
});
