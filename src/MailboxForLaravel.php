<?php

namespace Redberry\MailboxForLaravel;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class MailboxForLaravel implements TransportInterface
{
    public function __toString()
    {
        return 'mailbox-for-laravel';
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $email = $message->toString();
        dd('MailboxForLaravel transport is used to send emails. This is a placeholder implementation.'.$email);
    }
}
