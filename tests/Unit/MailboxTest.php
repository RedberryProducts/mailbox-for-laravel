<?php

use Illuminate\Support\Facades\App;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(Mailbox::class, function () {
    it('proxies list/find/delete to CaptureService', function () {
        $mock = Mockery::mock(CaptureService::class);
        $mock->shouldReceive('list')->once()->andReturn([]);
        $mock->shouldReceive('find')->with('id')->once()->andReturn(null);
        $mock->shouldReceive('delete')->with('id')->once();
        App::instance(CaptureService::class, $mock);

        Mailbox::list();
        Mailbox::find('id');
        Mailbox::delete('id');
    });

    it('returns paginated results when requested', function () {
        $store = new FileStorage(sys_get_temp_dir().'/mailbox-facade-'.uniqid());
        $svc = new CaptureService($store);
        App::instance(CaptureService::class, $svc);

        $svc->store(['raw' => 'one', 'timestamp' => 1000]);
        $svc->store(['raw' => 'two', 'timestamp' => 2000]);

        $page = Mailbox::list(page: 2, perPage: 1);
        expect($page)->toBeArray()
            ->and(count($page))->toBe(1);
    });

    it('can retrieve all messages', function () {
        $store = new FileStorage(sys_get_temp_dir().'/inbox-facade-all-'.uniqid());
        $svc = new CaptureService($store);
        App::instance(CaptureService::class, $svc);

        $svc->store(['raw' => 'message1']);
        $svc->store(['raw' => 'message2']);

        $all = Mailbox::all();
        expect($all)->toHaveCount(2);
    });
});
