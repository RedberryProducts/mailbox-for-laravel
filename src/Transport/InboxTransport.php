<?php

namespace Redberry\MailboxForLaravel\Transport;

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Support\MessageNormalizer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class InboxTransport extends AbstractTransport
{
    public function __construct(protected CaptureService $mailbox)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'inbox';
    }

    protected function doSend(SentMessage $message): void
    {
        $raw = $message->toString();
        $original = $message->getOriginalMessage();
        $envelope = $message->getEnvelope();
        $payload = MessageNormalizer::normalize($original, $envelope, $raw, true);

        $this->mailbox->store($payload);
    }
}
