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

        // Ensure parent directory exists
        $parentDir = dirname($link);
        if (!File::exists($parentDir)) {
            File::makeDirectory($parentDir, 0755, true);
        }

        // FULL CLEANUP — handle directories, files, symlinks, ghost links
        $this->cleanup($link);

        // Create the symlink
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
        // If it doesn't exist at all, nothing to clean
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        // If it is a symlink (even broken) — unlink it
        if (is_link($path)) {
            @unlink($path);
            return;
        }

        // If it is a directory — delete it entirely
        if (is_dir($path)) {
            File::deleteDirectory($path);
            return;
        }

        // Fallback: if it's a regular file — remove it
        @unlink($path);
    }
}
