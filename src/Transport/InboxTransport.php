<?php

namespace Redberry\MailboxForLaravel\Transport;

use Redberry\MailboxForLaravel\CaptureService;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class InboxTransport implements TransportInterface
{
    public function __construct(protected CaptureService $mailbox)
    {
    }

    public function __toString(): string
    {
        return 'inbox';
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $raw = $message->toString();
        $this->mailbox->storeRaw($raw);

        return new SentMessage($message, $envelope);
    }
}
