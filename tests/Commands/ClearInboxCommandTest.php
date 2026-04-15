<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mockery as M;
use Redberry\MailboxForLaravel\CaptureService;

beforeEach(function () {
    config()->set('mailbox.retention.seconds', 3600);

    Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));
});

afterEach(function () {
    M::close();
    Carbon::setTestNow();
});

it('clears all messages and attachments through CaptureService', function () {
    $service = M::mock(CaptureService::class);
    $service->shouldReceive('clearAll')->once();

    app()->instance(CaptureService::class, $service);

    expect(Artisan::call('mailbox:clear'))->toBe(0);
});

it('purges only outdated messages when --outdated is used', function () {
    $service = M::mock(CaptureService::class);
    $service->shouldReceive('purgeOlderThan')
        ->once()
        ->with(3600);

    app()->instance(CaptureService::class, $service);

    expect(Artisan::call('mailbox:clear', ['--outdated' => true]))->toBe(0);
});

it('returns SUCCESS exit code', function () {
    $service = M::mock(CaptureService::class);
    $service->shouldReceive('clearAll')->once();

    app()->instance(CaptureService::class, $service);

    $exitCode = Artisan::call('mailbox:clear');

    expect($exitCode)->toBe(0);
});
