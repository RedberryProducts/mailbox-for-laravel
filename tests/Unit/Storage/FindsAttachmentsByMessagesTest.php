<?php

use Redberry\MailboxForLaravel\DTO\StoredAttachment;
use Redberry\MailboxForLaravel\Storage\Concerns\FindsAttachmentsByMessages;

describe(FindsAttachmentsByMessages::class, function () {
    it('falls back to one findByMessage call per message id', function () {
        $store = new class
        {
            use FindsAttachmentsByMessages;

            /** @var array<int, int|string> */
            public array $calls = [];

            /** @return array<int, StoredAttachment> */
            public function findByMessage(int|string $messageId): array
            {
                $this->calls[] = $messageId;

                return $messageId === 'a'
                    ? [new StoredAttachment('att-1', 'a', 'a.txt', 'text/plain', 1, 'mailbox', 'a.txt', null, false)]
                    : [];
            }
        };

        $records = $store->findByMessages(['a', 'b']);

        expect($store->calls)->toBe(['a', 'b'])
            ->and(array_keys($records))->toBe(['a', 'b'])
            ->and($records['a'][0]->filename)->toBe('a.txt')
            ->and($records['b'])->toBe([]);
    });
});
