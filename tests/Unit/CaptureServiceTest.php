<?php

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
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
        $msg = $svc->find($key);
        expect($msg)->toBeInstanceOf(MailboxMessageData::class);
        expect($msg->raw)->toBe('hello');
    });

    it('lists all messages ordered by timestamp desc', function () {
        $svc = service();
        $svc->store(['raw' => 'one', 'timestamp' => 1000]);
        $svc->store(['raw' => 'two', 'timestamp' => 2000]);

        $all = $svc->all();
        expect(count($all))->toBe(2);
        expect($all[0]->raw)->toBe('two'); // newest first
        expect($all[1]->raw)->toBe('one');
    });

    it('finds a message by id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'foo']);

        $msg = $svc->find($key);
        expect($msg)->toBeInstanceOf(MailboxMessageData::class);
        expect($msg->raw)->toBe('foo');
    });

    it('deletes a message by id', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'bar']);
        $svc->delete($key);

        expect($svc->find($key))->toBeNull();
    });

    it('stores raw string directly using storeRaw method', function () {
        $svc = service();
        $key = $svc->storeRaw('raw email content');

        expect($key)->not->toBeEmpty();
        $retrieved = $svc->find($key);
        expect($retrieved)->toBeInstanceOf(MailboxMessageData::class);
        expect($retrieved->raw)->toBe('raw email content');
    });

    it('returns all messages when list called with default perPage', function () {
        $svc = service();
        $svc->store(['raw' => 'message1']);
        $svc->store(['raw' => 'message2']);
        $svc->store(['raw' => 'message3']);

        $result = $svc->list();

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['data', 'total', 'per_page', 'current_page', 'has_more', 'latest_timestamp'])
            ->and($result['data'])->toHaveCount(3)
            ->and($result['data'][0])->toBeInstanceOf(MailboxMessageData::class)
            ->and($result['total'])->toBe(3)
            ->and($result['has_more'])->toBeFalse();
    });

    it('returns paginated results when perPage is specified', function () {
        $svc = service();
        $svc->store(['raw' => 'message1', 'timestamp' => 1000]);
        $svc->store(['raw' => 'message2', 'timestamp' => 2000]);
        $svc->store(['raw' => 'message3', 'timestamp' => 3000]);

        $result = $svc->list(1, 2);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['data', 'total', 'per_page', 'current_page', 'has_more', 'latest_timestamp'])
            ->and($result['data'])->toHaveCount(2)
            ->and($result['data'][0])->toBeInstanceOf(MailboxMessageData::class)
            ->and($result['total'])->toBe(3)
            ->and($result['has_more'])->toBeTrue()
            ->and($result['latest_timestamp'])->toBe(3000);
    });

    it('marks message as seen', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'test']);

        $updated = $svc->markSeen($key);

        expect($updated)->toBeInstanceOf(MailboxMessageData::class);
        expect($updated->seen_at)->not->toBeNull();
    });

    it('updates message with partial changes', function () {
        $svc = service();
        $key = $svc->store(['raw' => 'test', 'custom_field' => 'old']);

        $updated = $svc->update($key, ['custom_field' => 'new']);

        expect($updated)->toBeInstanceOf(MailboxMessageData::class);
        expect($updated->raw)->toBe('test');
    });

    it('purges messages older than given seconds', function () {
        $svc = service();
        $oldKey = $svc->store(['raw' => 'old', 'timestamp' => time() - 100]);
        $newKey = $svc->store(['raw' => 'new', 'timestamp' => time()]);

        $svc->purgeOlderThan(50);

        expect($svc->find($oldKey))->toBeNull();
        expect($svc->find($newKey))->not->toBeNull();
    });

    it('clears all messages', function () {
        $svc = service();
        $svc->store(['raw' => 'one']);
        $svc->store(['raw' => 'two']);

        $svc->clearAll();

        expect($svc->all())->toBeEmpty();
    });
});
