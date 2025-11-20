<?php

namespace Redberry\MailboxForLaravel\Transport;

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;
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

    protected function doSend(SentMessage $message): void
    {
        $raw = $message->toString();
        $original = $message->getOriginalMessage();
        $envelope = $message->getEnvelope();

        if ($this->enabled) {
            // Normalize message (attachments stored as metadata only for now)
            $payload = MessageNormalizer::normalize($original, $envelope, $raw, false);

            // Store message first to get ID
            $this->storedKey = $this->mailbox->store($payload);

            // Extract and store attachments separately if enabled and it's an Email
            if (config('mailbox.attachments.enabled', true) && $original instanceof Email) {
                $attachments = MessageNormalizer::extractAttachments($original);
                foreach ($attachments as $attachment) {
                    $this->attachmentStore->store($this->storedKey, $attachment);
                }
            }
        }

        if ($this->decorated) {
            $this->decorated->send($message->getOriginalMessage(), $message->getEnvelope());
        }
    }
}
