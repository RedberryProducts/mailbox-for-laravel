<?php

namespace Redberry\MailboxForLaravel;

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

class StoreManager
{
    public function create(): MessageStore
    {
        $driver = config('mailbox-for-laravel.storage_driver', 'file');

        return match ($driver) {
            'file' => new FileStorage(config('mailbox-for-laravel.storage_path')),
            default => throw new \InvalidArgumentException("Unsupported storage driver [{$driver}]"),
        };
    }
}
