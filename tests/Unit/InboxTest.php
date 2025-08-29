<?php

use Illuminate\Support\Facades\App;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Facades\Inbox;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(Inbox::class, function () {
    it('proxies list/get/delete to CaptureService', function () {
        $mock = Mockery::mock(CaptureService::class);
        $mock->shouldReceive('list')->once()->andReturn([]);
        $mock->shouldReceive('get')->with('id')->once()->andReturn(['foo']);
        $mock->shouldReceive('delete')->with('id')->once();
        App::instance(CaptureService::class, $mock);

        Inbox::list();
        Inbox::get('id');
        Inbox::delete('id');
    });

    it('returns paginated results when requested', function () {
        $store = new FileStorage(sys_get_temp_dir().'/inbox-facade-'.uniqid());
        $svc = new CaptureService($store);
        App::instance(CaptureService::class, $svc);

        $svc->store(['raw' => 'one']);
        $svc->store(['raw' => 'two']);

        $page = Inbox::list(page: 2, perPage: 1);
        expect($page['total'])->toBe(2)
            ->and(count($page['data']))->toBe(1);
    });

    it('guards against invalid ids', function () {
        $store = new FileStorage(sys_get_temp_dir().'/inbox-facade-invalid-'.uniqid());
        $svc = new CaptureService($store);
        App::instance(CaptureService::class, $svc);

        expect(fn () => Inbox::get('../bad'))->toThrow(InvalidArgumentException::class);
        expect(fn () => Inbox::delete('bad/..'))->toThrow(InvalidArgumentException::class);
    });
});
