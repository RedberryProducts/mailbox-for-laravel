<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Redberry\MailboxForLaravel\Transport\MailboxTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendTestMailController
{
    /**
     * Capture a sample email through the real transport pipeline.
     *
     * The message goes through MailboxTransport (normalizer, storage and
     * attachments) but is never forwarded to a decorated mailer, so the
     * button works in decorate mode without delivering anything.
     */
    public function __invoke(MailboxTransport $transport): JsonResponse
    {
        $email = (new Email)
            ->from(new Address('hello@example.com', 'Laravel'))
            ->to('recipient@example.com')
            ->subject('Test Mailbox for Laravel')
            ->text('Hello from Mailbox for Laravel. This is a test email.')
            ->html('<h1>Hello from Mailbox for Laravel</h1><p>This is a test email.</p>');

        $key = $transport->captureOnly($email);

        return response()->json([
            'status' => 'stored',
            'key' => $key,
        ]);
    }
}
