<?php

use Illuminate\Support\Facades\Facade;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\DatabaseAttachmentStore;
use Redberry\MailboxForLaravel\Storage\DatabaseMessageStore;
use Redberry\MailboxForLaravel\Storage\FileAttachmentStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

const PACKAGE = 'Redberry\MailboxForLaravel';

/**
 * Package source files, keyed by path relative to the package root.
 *
 * @return array<string, string>
 */
function packageFiles(string $directory): array
{
    $root = dirname(__DIR__, 2);
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[str_replace($root.'/', '', str_replace('\\', '/', $file->getPathname()))] = (string) file_get_contents($file->getPathname());
        }
    }

    ksort($files);

    return $files;
}

describe('layer boundaries', function () {
    arch('the HTTP layer does not depend on storage implementations or models')
        ->expect(PACKAGE.'\Http')
        ->not->toUse([PACKAGE.'\Storage', PACKAGE.'\Models', PACKAGE.'\StoreManager']);

    arch('storage does not depend on the HTTP layer')
        ->expect(PACKAGE.'\Storage')
        ->not->toUse([PACKAGE.'\Http', 'Illuminate\Http', 'Illuminate\Routing', 'Symfony\Component\HttpFoundation']);

    arch('support classes do not depend on the HTTP layer')
        ->expect(PACKAGE.'\Support')
        ->not->toUse([PACKAGE.'\Http', 'Illuminate\Http', 'Illuminate\Routing']);

    /*
     * One arch() per target: Pest silently passes an expect() array that
     * mixes a class with a namespace, so each target gets its own rule.
     */
    foreach ([PACKAGE.'\CaptureService', PACKAGE.'\Support', PACKAGE.'\Transport', PACKAGE.'\Testing'] as $target) {
        arch("{$target} depends on contracts, not on storage implementations or models")
            ->expect($target)
            ->not->toUse([PACKAGE.'\Storage', PACKAGE.'\Models', PACKAGE.'\StoreManager']);
    }

    foreach ([PACKAGE.'\CaptureService', PACKAGE.'\Support', PACKAGE.'\Transport', PACKAGE.'\Contracts', PACKAGE.'\DTO'] as $target) {
        arch("{$target} does not use facades")
            ->expect($target)
            ->not->toUse('Illuminate\Support\Facades');
    }

    foreach ([PACKAGE.'\CaptureService', PACKAGE.'\Support', PACKAGE.'\Contracts', PACKAGE.'\DTO'] as $target) {
        arch("{$target} does not read config directly")
            ->expect($target)
            ->not->toUse('config');
    }

    foreach ([PACKAGE.'\Contracts', PACKAGE.'\DTO', PACKAGE.'\CaptureService', PACKAGE.'\Support', PACKAGE.'\Storage', PACKAGE.'\Transport'] as $target) {
        arch("{$target} does not depend on views or routing")
            ->expect($target)
            ->not->toUse(['Illuminate\View', 'Illuminate\Contracts\View', 'Illuminate\Routing']);
    }

    arch('controllers do not depend on the mailer')
        ->expect(PACKAGE.'\Http')
        ->not->toUse(['Illuminate\Mail', 'Illuminate\Support\Facades\Mail', 'Symfony\Component\Mailer']);
});

describe('who may use what', function () {
    arch('the transport is only wired up by the service provider')
        ->expect(PACKAGE.'\Transport')
        ->toOnlyBeUsedIn(PACKAGE.'\MailboxServiceProvider');

    arch('middleware is only registered by the service provider')
        ->expect(PACKAGE.'\Http\Middleware')
        ->toOnlyBeUsedIn(PACKAGE.'\MailboxServiceProvider');

    arch('controllers are not used by other layers')
        ->expect(PACKAGE.'\Http\Controllers')
        ->toOnlyBeUsedIn(PACKAGE.'\Http\Controllers');

    arch('message store implementations are only resolved by the store manager')
        ->expect([DatabaseMessageStore::class, FileStorage::class])
        ->toOnlyBeUsedIn([PACKAGE.'\StoreManager', PACKAGE.'\Storage']);

    arch('attachment store implementations are only bound by the service provider')
        ->expect([DatabaseAttachmentStore::class, FileAttachmentStore::class])
        ->toOnlyBeUsedIn([PACKAGE.'\MailboxServiceProvider', PACKAGE.'\Storage']);
});

describe('contracts and implementations', function () {
    arch('message stores implement the MessageStore contract')
        ->expect([DatabaseMessageStore::class, FileStorage::class])
        ->toImplement(MessageStore::class);

    arch('attachment stores implement the AttachmentStore contract')
        ->expect([DatabaseAttachmentStore::class, FileAttachmentStore::class])
        ->toImplement(AttachmentStore::class);

    arch('contracts are interfaces')
        ->expect(PACKAGE.'\Contracts')
        ->toBeInterfaces();

    arch('facades extend the Laravel facade')
        ->expect(PACKAGE.'\Facades')
        ->toExtend(Facade::class);
});

describe('naming', function () {
    arch('controllers end with Controller')
        ->expect(PACKAGE.'\Http\Controllers')
        ->toHaveSuffix('Controller');

    arch('middleware ends with Middleware')
        ->expect(PACKAGE.'\Http\Middleware')
        ->toHaveSuffix('Middleware');

    arch('commands end with Command')
        ->expect(PACKAGE.'\Commands')
        ->toHaveSuffix('Command');

    it('names every test file *Test.php', function () {
        $helpers = ['tests/Pest.php', 'tests/TestCase.php'];

        $misnamed = array_filter(
            array_keys(packageFiles('tests')),
            static fn (string $path): bool => ! in_array($path, $helpers, true) && ! str_ends_with($path, 'Test.php'),
        );

        expect($misnamed)->toBe([]);
    });
});

describe('code hygiene', function () {
    arch('source files declare strict types')
        ->expect(PACKAGE)
        ->toUseStrictTypes();

    arch('no debugging calls in source')
        ->expect(PACKAGE)
        ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'var_export', 'die']);

    arch('the package does not make network calls')
        ->expect(PACKAGE)
        ->not->toUse(['GuzzleHttp', 'Illuminate\Http\Client', 'Illuminate\Support\Facades\Http', 'curl_init', 'fsockopen']);

    arch('public API does not depend on test frameworks outside Testing')
        ->expect(PACKAGE)
        ->not->toUse(['PHPUnit', 'Pest', 'Orchestra\Testbench'])
        ->ignoring(PACKAGE.'\Testing');

    it('has no hard-coded absolute paths in source', function () {
        $offenders = array_keys(array_filter(
            packageFiles('src'),
            static fn (string $code): bool => (bool) preg_match('#[\'"](/(Users|home|var|tmp|opt|usr|private)/|[A-Za-z]:\\\\)#', $code),
        ));

        expect($offenders)->toBe([]);
    });

    it('has no TODO or FIXME comments in source', function () {
        $offenders = array_keys(array_filter(
            packageFiles('src'),
            static fn (string $code): bool => (bool) preg_match('/\b(TODO|FIXME|XXX)\b/', $code),
        ));

        expect($offenders)->toBe([]);
    });

    it('has no inline PHPStan suppressions in source', function () {
        $offenders = array_keys(array_filter(
            packageFiles('src'),
            static fn (string $code): bool => str_contains($code, '@phpstan-ignore'),
        ));

        expect($offenders)->toBe([]);
    });
});
