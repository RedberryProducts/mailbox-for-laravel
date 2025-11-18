<?php

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DevLinkCommand extends Command
{
    protected $signature = 'mailbox:dev-link';

    protected $description = 'Symlink package assets for development (cleans old links first)';

    public function handle(): int
    {
        $target = base_path('packages/redberry/mailbox-for-laravel/public/vendor/mailbox');
        $link = public_path('vendor/mailbox');

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
     * Clean an existing path: file, directory, or broken symlink.
     */
    protected function cleanup(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        if (is_link($path)) {
            @unlink($path);

            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);

            return;
        }

        @unlink($path);
    }
}
