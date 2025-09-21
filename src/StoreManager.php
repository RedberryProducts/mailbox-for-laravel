<?php

namespace Redberry\MailboxForLaravel;

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

class StoreManager
{
    public function create(): MessageStore
    {
        $driver = config('inbox.store.driver', 'file');
        $resolvers = config('inbox.store.resolvers', []);

        // Check for a custom resolver first, if available and it should implement MessageStore
        $customStore = $this->resolveCustomStore($driver, $resolvers);
        if ($customStore !== null) {
            return $customStore;
        }

        return match ($driver) {
            'file' => new FileStorage(config('inbox.store.file.path')),
            default => throw new \InvalidArgumentException("Unsupported storage driver [{$driver}]"),
        };
    }

    private function resolveCustomStore($driver, $resolvers): ?MessageStore
    {
        if (!isset($resolvers[$driver]) || !is_callable($resolvers[$driver])) {
            return null;
        }

        $store = $resolvers[$driver]();
        if (!$store instanceof MessageStore) {
            return null;
        }

        return $store;
    }
}
