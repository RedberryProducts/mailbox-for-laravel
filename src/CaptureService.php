<?php

namespace Redberry\MailboxForLaravel;

use Redberry\MailboxForLaravel\Contracts\MessageStore;

class CaptureService
{
    public function __construct(protected MessageStore $storage) {}

    /**
     * Persist the raw message and metadata.
     */
    public function storeRaw(string $raw): string
    {
        $key = 'email_'.md5($raw).'_'.microtime(true);

        $this->storage->store($key, [
            'timestamp' => time(),
            'raw' => $raw,
        ]);

        return $key;
    }
}
