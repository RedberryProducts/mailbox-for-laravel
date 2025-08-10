<?php

use Redberry\MailboxForLaravel\StoreManager;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

it('creates a file store when driver is file', function () {
    config()->set('inbox.store.driver', 'file');
    config()->set('inbox.store.file.path', sys_get_temp_dir() . '/inbox-test-' . uniqid());

    $manager = new StoreManager();
    $store = $manager->create();

    expect($store)->toBeInstanceOf(MessageStore::class)
        ->toBeInstanceOf(FileStorage::class);
});
