<?php

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(CaptureService::class, function () {
    function service(): CaptureService {
        $path = sys_get_temp_dir().'/mailbox-capture-tests-'.uniqid();
        $store = new FileStorage($path);
        return new CaptureService($store);
    }

    it('stores raw message and returns key', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'hello']);

        expect($key)->not->toBeEmpty();
        expect($svc->retrieve($key)['raw'])->toBe('hello');
    });

    it('lists all messages ordered by timestamp desc', function () {
        $svc = service();
        $svc->store(['raw' => 'one']);
        $svc->store(['raw' => 'two']);

        $all = $svc->all();
        expect(count($all))->toBe(2);
    });

    it('finds a message by id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'foo']);

        expect($svc->retrieve($key)['raw'])->toBe('foo');
    });

    it('deletes a message by id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'bar']);
        $svc->delete($key);

        expect($svc->retrieve($key))->toBeNull();
    });
});
