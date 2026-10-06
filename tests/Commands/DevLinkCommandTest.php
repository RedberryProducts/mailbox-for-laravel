<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Redberry\MailboxForLaravel\Commands\DevLinkCommand;

describe(DevLinkCommand::class, function () {
    /**
     * Point the command at a throwaway assets directory so the tests never
     * link to (and then write through to) the package's real build output.
     */
    function useAssetsPath(string $path): void
    {
        app()->bind(DevLinkCommand::class, fn () => new class($path) extends DevLinkCommand
        {
            public function __construct(private string $fakeAssetsPath)
            {
                parent::__construct();
            }

            protected function assetsPath(): string
            {
                return $this->fakeAssetsPath;
            }
        });
    }

    function removeLink(): void
    {
        $link = public_path('vendor/mailbox');

        if (is_link($link)) {
            unlink($link);
        } elseif (is_dir($link)) {
            File::deleteDirectory($link);
        }
    }

    beforeEach(function () {
        removeLink();
        $this->assetsPath = sys_get_temp_dir().'/mailbox-dev-link-'.uniqid();
    });

    afterEach(function () {
        removeLink();
        File::deleteDirectory($this->assetsPath);
    });

    it('is registered outside the local environment', function () {
        expect(app()->environment('local'))->toBeFalse()
            ->and(Artisan::all())->toHaveKey('mailbox:dev-link');
    });

    it('links public/vendor/mailbox to the package assets', function () {
        File::makeDirectory($this->assetsPath, 0755, true);
        useAssetsPath($this->assetsPath);

        $this->artisan('mailbox:dev-link')->assertExitCode(Command::SUCCESS);

        $link = public_path('vendor/mailbox');

        expect(is_link($link))->toBeTrue()
            ->and(readlink($link))->toBe($this->assetsPath);
    });

    it('fails without creating a dangling link when the assets are missing', function () {
        useAssetsPath($this->assetsPath);

        $this->artisan('mailbox:dev-link')
            ->expectsOutputToContain('Mailbox assets not found')
            ->assertExitCode(Command::FAILURE);

        expect(is_link(public_path('vendor/mailbox')))->toBeFalse();
    });

    it('resolves the assets from the package itself by default', function () {
        $method = new ReflectionMethod(DevLinkCommand::class, 'assetsPath');

        expect(realpath($method->invoke(new DevLinkCommand)))
            ->toBe(realpath(__DIR__.'/../../public/vendor/mailbox'));
    });

    it('makes mailbox:install --dev fail when linking fails', function () {
        useAssetsPath($this->assetsPath);

        $this->artisan('mailbox:install', ['--dev' => true])
            ->doesntExpectOutput('Mailbox migrations run.')
            ->assertExitCode(Command::FAILURE);
    });
});
