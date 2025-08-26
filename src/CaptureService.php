<?php

namespace Redberry\MailboxForLaravel;

use Redberry\MailboxForLaravel\Contracts\MessageStore;

class CaptureService
{
    public function __construct(protected MessageStore $storage) {}

    /**
     * Persist the raw message and metadata.
     */
    public function store(array $payload): string
    {
        $raw = $payload['raw'] ?? '';
        $key = 'email_'.md5($raw).'_'.microtime(true);

        $this->storage->store($key, [
            'timestamp' => time(),
            ...$payload,
        ]);

        return $key;
    }

    /**
     * Retrieve a stored message by its key.
     */
    public function retrieve(string $key): ?array
    {
        return $this->storage->retrieve($key);
    }

    public function all()
    {
        $messages = [];
        foreach ($this->storage->keys() as $key) {
            $messages[$key] = $this->retrieve($key);
        }

        return $messages;
    }
}
