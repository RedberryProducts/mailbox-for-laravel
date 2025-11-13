<?php

use Illuminate\Support\Facades\App;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe(Mailbox::class, function () {
    it('proxies list/get/delete to CaptureService', function () {
        $mock = Mockery::mock(CaptureService::class);
        $mock->shouldReceive('list')->once()->andReturn([]);
        $mock->shouldReceive('get')->with('id')->once()->andReturn(['foo']);
        $mock->shouldReceive('delete')->with('id')->once();
        App::instance(CaptureService::class, $mock);

        Mailbox::list();
        Mailbox::get('id');
        Mailbox::delete('id');
    });

    it('returns paginated results when requested', function () {
        $store = new FileStorage(sys_get_temp_dir().'/inbox-facade-'.uniqid());
        $svc = new CaptureService($store);
        App::instance(CaptureService::class, $svc);

        $svc->store(['raw' => 'one']);
        $svc->store(['raw' => 'two']);

        $page = Mailbox::list(page: 2, perPage: 1);
        expect($page['total'])->toBe(2)
            ->and(count($page['data']))->toBe(1);
    });

    it('guards against invalid ids', function () {
        $store = new FileStorage(sys_get_temp_dir().'/inbox-facade-invalid-'.uniqid());
        $svc = new CaptureService($store);
        App::instance(CaptureService::class, $svc);

        expect(fn () => Mailbox::get('../bad'))->toThrow(InvalidArgumentException::class);
        expect(fn () => Mailbox::delete('bad/..'))->toThrow(InvalidArgumentException::class);
    });
});
