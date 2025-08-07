<?php

namespace Redberry\MailboxForLaravel;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        return new MailboxForLaravel();
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'mailbox-for-laravel';
    }
}
