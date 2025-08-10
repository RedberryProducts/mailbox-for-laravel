<?php

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Redberry\MailboxForLaravel\CaptureService;

class TestMailable extends Mailable
{
    public function build()
    {
        return $this->subject('Hello')->html('<p>Hello</p>');
    }
}

it('routes mail through inbox transport and calls CaptureService', function () {
    $captured = (object) ['raw' => null];

    $this->app->singleton(CaptureService::class, function () use ($captured) {
        return new class($captured) extends CaptureService {
            public function __construct(private object $captured) {}
            public function storeRaw(string $raw): string
            {
                $this->captured->raw = $raw;
                return 'k';
            }
        };
    });

    Mail::to('user@example.com')->send(new TestMailable());

    expect($captured->raw)->not->toBeNull()
        ->and($captured->raw)->toContain('Hello');
});
