<?php

namespace Redberry\MailboxForLaravel;

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

class StoreManager
{
    public function create(): MessageStore
    {
        $driver = config('inbox.store.driver', 'file');
        $options = config('inbox.store', []);

        $resolvers = config('inbox.store.resolvers', []);
        if (isset($resolvers[$driver]) && is_callable($resolvers[$driver])) {
            return $resolvers[$driver]($options);
        }

        return match ($driver) {
            'file' => new FileStorage($options['file']['path'] ?? config('inbox.store.file.path', storage_path('app/inbox'))),
            default => throw new \InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }
}
