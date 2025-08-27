<?php

use Redberry\MailboxForLaravel\StoreManager;

describe(StoreManager::class, function () {
    it('creates a file-based MessageStore when driver=file')->todo();
    it('throws when an unknown driver is configured')->todo();
    it('accepts a custom driver resolver via config')->todo();
    it('passes configuration options to store implementations')->todo();
});
