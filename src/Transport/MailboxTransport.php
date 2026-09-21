<?php

namespace Redberry\MailboxForLaravel\Transport;

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Redberry\MailboxForLaravel\Support\MessageNormalizer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class MailboxTransport extends AbstractTransport
{
    protected ?string $storedKey = null;

    public function __construct(
        protected CaptureService $mailbox,
        protected AttachmentStore $attachmentStore,
        protected ?TransportInterface $decorated = null,
        protected bool $enabled = true
    ) {
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

    /**
     * Capture the message, then forward it to the decorated transport.
     *
     * Capture is best-effort when a decorated transport is configured: a
     * storage failure is reported and real delivery still happens. Without a
     * decorated transport the failure is rethrown, since nothing else would
     * deliver the message.
     */
    protected function doSend(SentMessage $message): void
    {
        $this->storedKey = null;

        if ($this->enabled) {
            try {
                $this->capture($message);
            } catch (\Throwable $e) {
                if ($this->decorated === null) {
                    throw $e;
                }

                report($e);
            }
        }

        if ($this->decorated) {
            $this->decorated->send($message->getOriginalMessage(), $message->getEnvelope());
        }
    }

    /**
     * Store the message and its attachments, recording the storage key.
     */
    protected function capture(SentMessage $message): void
    {
        $raw = $message->toString();
        $original = $message->getOriginalMessage();
        $envelope = $message->getEnvelope();

        $payload = MessageNormalizer::normalize($original, $envelope, $raw, false);

        $this->storedKey = $this->mailbox->store($payload);

        if (config('mailbox.attachments.enabled', true) && $original instanceof Email) {
            $attachments = MessageNormalizer::extractAttachments($original);
            foreach ($attachments as $attachment) {
                $this->attachmentStore->store($this->storedKey, $attachment);
            }
        }
    }
}
