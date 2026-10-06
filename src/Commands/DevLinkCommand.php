<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DevLinkCommand extends Command
{
    protected $signature = 'mailbox:dev-link';

    protected $description = 'Symlink package assets for development (cleans old links first)';

    public function handle(): int
    {
        $target = $this->assetsPath();
        $link = public_path('vendor/mailbox');

        if (! is_dir($target)) {
            $this->error("Mailbox assets not found at [{$target}]. Run `npm run build` in the package first.");

            return Command::FAILURE;
        }

        $parentDir = dirname($link);
        if (! File::exists($parentDir)) {
            File::makeDirectory($parentDir, 0755, true);
        }

        $this->cleanup($link);

        symlink($target, $link);

        $this->info('Mailbox dev assets linked:');
        $this->info("$link  →  $target");

        return Command::SUCCESS;
    }

    /**
     * The package's own built assets, wherever the package is installed.
     */
    protected function assetsPath(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'mailbox';
    }

    /**
     * Clean an existing path: file, directory, or broken symlink.
     */
    protected function cleanup(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);

            return;
        }

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
