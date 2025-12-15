<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery as M;
use Redberry\MailboxForLaravel\Contracts\MessageStore;

beforeEach(function () {
    Storage::fake('mailbox');

    config()->set('mailbox.attachments.enabled', true);
    config()->set('mailbox.attachments.disk', 'mailbox');
    config()->set('mailbox.attachments.path', 'attachments');
    config()->set('mailbox.retention.seconds', 3600);

    Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));
});

afterEach(function () {
    M::close();
    Carbon::setTestNow();
});

it('clears all messages and deletes attachment directory', function () {
    $store = M::mock(MessageStore::class);
    $store->shouldReceive('clear')->once();

    app()->instance(MessageStore::class, $store);

    Storage::disk('mailbox')->put('attachments/foo.txt', 'data');
    Storage::disk('mailbox')->put('attachments/bar.txt', 'data');

    expect(Storage::disk('mailbox')->exists('attachments'))->toBeTrue();

    Artisan::call('mailbox:clear');

    expect(Storage::disk('mailbox')->exists('attachments'))->toBeFalse();
});

it('clears only outdated messages when --outdated is used', function () {
    $store = M::mock(MessageStore::class);
    $store->shouldReceive('purgeOlderThan')
        ->once()
        ->with(3600);

    app()->instance(MessageStore::class, $store);

    Artisan::call('mailbox:clear', ['--outdated' => true]);
});

it('deletes only outdated attachment files when --outdated is used', function () {
    $disk = Storage::disk('mailbox');

    // Old file (2 hours ago)
    $oldFile = 'attachments/old.txt';
    $disk->put($oldFile, 'old');
    touch($disk->path($oldFile), Carbon::now()->subHours(2)->timestamp);

    // New file (10 minutes ago)
    $newFile = 'attachments/new.txt';
    $disk->put($newFile, 'new');
    touch($disk->path($newFile), Carbon::now()->subMinutes(10)->timestamp);

    $store = M::mock(MessageStore::class);
    $store->shouldReceive('purgeOlderThan')->once();

    app()->instance(MessageStore::class, $store);

    Artisan::call('mailbox:clear', ['--outdated' => true]);

    expect($disk->exists($oldFile))->toBeFalse()
        ->and($disk->exists($newFile))->toBeTrue();
});

it('does nothing with attachments if attachments are disabled', function () {
    config()->set('mailbox.attachments.enabled', false);

    $store = M::mock(MessageStore::class);
    $store->shouldReceive('clear')->once();

    app()->instance(MessageStore::class, $store);

    Storage::disk('mailbox')->put('attachments/file.txt', 'data');

    Artisan::call('mailbox:clear');

    expect(Storage::disk('mailbox')->exists('attachments/file.txt'))->toBeTrue();
});

it('does not fail if attachment path does not exist', function () {
    $store = M::mock(MessageStore::class);
    $store->shouldReceive('clear')->once();

    app()->instance(MessageStore::class, $store);

    expect(fn () => Artisan::call('mailbox:clear'))->not->toThrow(Throwable::class);
});

it('returns SUCCESS exit code', function () {
    $store = M::mock(MessageStore::class);
    $store->shouldReceive('clear')->once();

    app()->instance(MessageStore::class, $store);

    $exitCode = Artisan::call('mailbox:clear');

    expect($exitCode)->toBe(0);
});
