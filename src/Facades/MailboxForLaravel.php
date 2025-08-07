<?php

namespace Redberry\MailboxForLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Redberry\MailboxForLaravel\MailboxForLaravel
 */
class MailboxForLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Redberry\MailboxForLaravel\MailboxForLaravel::class;
    }
}
