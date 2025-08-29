<?php

namespace Redberry\MailboxForLaravel;

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

class StoreManager
{
    public function create(): MessageStore
    {
        $driver = config('mailbox-for-laravel.storage_driver', 'file');
        $options = config('mailbox-for-laravel.storage', []);

        $resolvers = config('mailbox-for-laravel.storage_resolvers', []);
        if (isset($resolvers[$driver]) && is_callable($resolvers[$driver])) {
            return $resolvers[$driver]($options);
        }

        return match ($driver) {
            'file' => new FileStorage($options['path'] ?? config('mailbox-for-laravel.storage_path')),
            default => throw new \InvalidArgumentException("Unsupported storage driver [{$driver}]"),
        };
    }
}
