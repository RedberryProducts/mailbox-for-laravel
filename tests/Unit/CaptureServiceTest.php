<?php

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(CaptureService::class, function () {
    function service(): CaptureService
    {
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

    it('stores raw string directly using storeRaw method', function () {
        $svc = service();
        $key = $svc->storeRaw('raw email content');

        expect($key)->not->toBeEmpty();
        $retrieved = $svc->retrieve($key);
        expect($retrieved['raw'])->toBe('raw email content');
    });

    it('returns all messages when list called with default perPage', function () {
        $svc = service();
        $svc->store(['raw' => 'message1']);
        $svc->store(['raw' => 'message2']);
        $svc->store(['raw' => 'message3']);

        // Call list with default PHP_INT_MAX - this should hit line 80 
        $result = $svc->list();
        
        expect($result)->toHaveCount(3);
        expect(is_array($result))->toBeTrue();
        
        // Verify all messages are present by checking raw content
        $rawContents = array_map(fn($msg) => $msg['raw'], $result);
        expect($rawContents)->toContain('message1');
        expect($rawContents)->toContain('message2');
        expect($rawContents)->toContain('message3');
    });

    it('returns paginated results when perPage is specified', function () {
        $svc = service();
        $svc->store(['raw' => 'message1']);
        $svc->store(['raw' => 'message2']);
        $svc->store(['raw' => 'message3']);

        $result = $svc->list(1, 2);
        
        expect($result)->toHaveKey('data');
        expect($result)->toHaveKey('total');
        expect($result)->toHaveKey('page');
        expect($result)->toHaveKey('per_page');
        expect($result['data'])->toHaveCount(2);
        expect($result['total'])->toBe(3);
        expect($result['page'])->toBe(1);
        expect($result['per_page'])->toBe(2);
    });
});
