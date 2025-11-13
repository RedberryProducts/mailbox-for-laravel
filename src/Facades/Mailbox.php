<?php

namespace Redberry\MailboxForLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Redberry\MailboxForLaravel\CaptureService;

class Mailbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CaptureService::class;
    }
}
