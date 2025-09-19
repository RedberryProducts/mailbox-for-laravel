<?php

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'mailbox:install {--force}';

    protected $description = 'Publish Mailbox assets to public/vendor/mailbox';

    public function handle(): int
    {
        $path = public_path('vendor/mailbox');

        if (File::exists($path)) {
            File::deleteDirectory($path);
        }

        $this->call('vendor:publish', [
            '--tag' => 'mailbox-assets',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Mailbox assets published.');

        return self::SUCCESS;
    }
}
