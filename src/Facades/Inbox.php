<?php

namespace Redberry\MailboxForLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Redberry\MailboxForLaravel\MailboxForLaravel
 */
class Inbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Redberry\MailboxForLaravel\InboxTranport::class;
    }
}
