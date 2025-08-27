<?php

use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(FileStorage::class, function () {
    it('generates deterministic unique keys for messages')->todo();
    it('writes raw and normalized payload atomically')->todo();
    it('retrieves a message by key')->todo();
    it('lists all messages sorted desc by timestamp')->todo();
    it('deletes a message and its assets')->todo();
    it('stores assets with stable public keys (messageKey/assetName)')->todo();
    it('retrieves asset binary streams and mime types')->todo();
    it('prevents directory traversal via sanitized keys and filenames')->todo();
    it('handles large attachments efficiently (streams, not loading fully in memory)')->todo();
    it('purges messages older than ttl and removes orphaned assets')->todo();
    it('recovers gracefully if a message file is partially missing')->todo();
});
