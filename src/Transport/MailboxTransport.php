<?php

namespace Redberry\MailboxForLaravel\Transport;

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Support\MessageNormalizer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailboxTransport extends AbstractTransport
{
    protected ?string $storedKey = null;

    public function __construct(protected CaptureService $mailbox, protected ?TransportInterface $decorated = null, protected bool $enabled = true)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'mailbox';
    }

    public function getStoredKey(): ?string
    {
        return $this->storedKey;
    }

    protected function doSend(SentMessage $message): void
    {
        $raw = $message->toString();
        $original = $message->getOriginalMessage();
        $envelope = $message->getEnvelope();

        if ($this->enabled) {
            $payload = MessageNormalizer::normalize($original, $envelope, $raw, true);
            $this->storedKey = $this->mailbox->store($payload);
        }

        if ($this->decorated) {
            $this->decorated->send($message->getOriginalMessage(), $message->getEnvelope());
        }
    }
}
