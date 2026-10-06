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

    if (is_file($root.'/'.$directory)) {
        return [$directory => (string) file_get_contents($root.'/'.$directory)];
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[str_replace($root.'/', '', str_replace('\\', '/', $file->getPathname()))] = (string) file_get_contents($file->getPathname());
        }
    }

    ksort($files);

    return $files;
}

/**
 * Source files under $path that reference any of the given vendor namespaces,
 * through an import or a fully qualified name.
 *
 * Vendor namespaces are checked by scanning source instead of with
 * arch()->not->toUse(): Pest autoloads every class in a vendor namespace it
 * is given, and some (e.g. Illuminate\Http\Client\Promises\FluentPromise
 * next to guzzlehttp/promises 1.x) fatal on prefer-lowest installs.
 *
 * @param  array<int, string>  $namespaces
 * @return array<int, string>
 */
function filesReferencing(string $path, array $namespaces): array
{
    $alternatives = implode('|', array_map(static fn (string $namespace): string => preg_quote($namespace, '/'), $namespaces));
    $pattern = '/(?<![\\w\\\\])\\\\?(?:'.$alternatives.')(?:\\\\|;|::|\\b)/';

    return array_keys(array_filter(
        packageFiles($path),
        static fn (string $code): bool => (bool) preg_match($pattern, $code),
    ));
}

/**
 * Source paths for the package's layers, relative to the package root.
 */
const LAYERS = [
    'CaptureService' => 'src/CaptureService.php',
    'Contracts' => 'src/Contracts',
    'DTO' => 'src/DTO',
    'Http' => 'src/Http',
    'Storage' => 'src/Storage',
    'Support' => 'src/Support',
    'Testing' => 'src/Testing',
    'Transport' => 'src/Transport',
];

describe('layer boundaries', function () {
    arch('the HTTP layer does not depend on storage implementations or models')
        ->expect(PACKAGE.'\Http')
        ->not->toUse([PACKAGE.'\Storage', PACKAGE.'\Models', PACKAGE.'\StoreManager']);

    foreach (['Storage', 'Support'] as $layer) {
        arch("{$layer} does not depend on the package HTTP layer")
            ->expect(PACKAGE.'\\'.$layer)
            ->not->toUse(PACKAGE.'\Http');

        it("keeps {$layer} free of framework HTTP classes", function () use ($layer) {
            expect(filesReferencing(LAYERS[$layer], ['Illuminate\Http', 'Illuminate\Routing', 'Symfony\Component\HttpFoundation']))->toBe([]);
        });
    }

    /*
     * One arch() per target: Pest silently passes an expect() array that
     * mixes a class with a namespace, so each target gets its own rule.
     */
    foreach ([PACKAGE.'\CaptureService', PACKAGE.'\Support', PACKAGE.'\Transport', PACKAGE.'\Testing'] as $target) {
        arch("{$target} depends on contracts, not on storage implementations or models")
            ->expect($target)
            ->not->toUse([PACKAGE.'\Storage', PACKAGE.'\Models', PACKAGE.'\StoreManager']);
    }

    foreach (['CaptureService', 'Support', 'Transport', 'Contracts', 'DTO'] as $layer) {
        it("keeps {$layer} free of facades", function () use ($layer) {
            expect(filesReferencing(LAYERS[$layer], ['Illuminate\Support\Facades']))->toBe([]);
        });
    }

    foreach ([PACKAGE.'\CaptureService', PACKAGE.'\Support', PACKAGE.'\Contracts', PACKAGE.'\DTO'] as $target) {
        arch("{$target} does not read config directly")
            ->expect($target)
            ->not->toUse('config');
    }

    foreach (['Contracts', 'DTO', 'CaptureService', 'Support', 'Storage', 'Transport'] as $layer) {
        it("keeps {$layer} free of views and routing", function () use ($layer) {
            expect(filesReferencing(LAYERS[$layer], ['Illuminate\View', 'Illuminate\Contracts\View', 'Illuminate\Routing']))->toBe([]);
        });
    }

    it('keeps controllers away from the mailer', function () {
        expect(filesReferencing(LAYERS['Http'], ['Illuminate\Mail', 'Illuminate\Support\Facades\Mail', 'Symfony\Component\Mailer']))->toBe([]);
    });
});

describe('who may use what', function () {
    arch('the transport is only used by the service provider and the test-email button')
        ->expect(PACKAGE.'\Transport')
        ->toOnlyBeUsedIn([PACKAGE.'\MailboxServiceProvider', PACKAGE.'\Http\Controllers\SendTestMailController']);

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

    it('does not make network calls', function () {
        $offenders = array_keys(array_filter(
            packageFiles('src'),
            static fn (string $code): bool => (bool) preg_match('/\bcurl_init\(|\bfsockopen\(/', $code),
        ));

        expect(filesReferencing('src', ['GuzzleHttp', 'Illuminate\Http\Client', 'Illuminate\Support\Facades\Http']))->toBe([])
            ->and($offenders)->toBe([]);
    });

    it('keeps test frameworks out of the public API outside Testing', function () {
        $offenders = array_filter(
            filesReferencing('src', ['PHPUnit', 'Pest', 'Mockery', 'Orchestra\Testbench']),
            static fn (string $path): bool => ! str_starts_with($path, 'src/Testing/'),
        );

        expect($offenders)->toBe([]);
    });

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
