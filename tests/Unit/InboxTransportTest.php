<?php

use Redberry\MailboxForLaravel\Transport\InboxTransport;
use Redberry\MailboxForLaravel\CaptureService;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;

class FakeCaptureService extends CaptureService
{
    public ?string $captured = null;

    public function __construct() {}
    public function storeRaw(string $raw): string
    {
        $this->captured = $raw;
        return 'k';
    }
}

it('forwards raw message to CaptureService and returns SentMessage', function () {
    $svc = new FakeCaptureService();
    $transport = new InboxTransport($svc);

    $msg = new RawMessage("Subject: T\r\n\r\nBody");
    $sent = $transport->send($msg, new Envelope(new Address('a@example.com'), [new Address('b@example.com')]));

    expect($svc->captured)->toContain('Subject: T')
        ->and($sent)->not->toBeNull();
});

it('has string name inbox', function () {
    $transport = new InboxTransport(new FakeCaptureService());
    expect((string) $transport)->toBe('inbox');
});
