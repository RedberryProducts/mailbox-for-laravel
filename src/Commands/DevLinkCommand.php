<?php

namespace Redberry\MailboxForLaravel\Commands;


use Illuminate\Console\Command;
use function PHPUnit\Framework\directoryExists;

class DevLinkCommand extends Command
{
    protected $signature = 'mailbox:dev-link';
    protected $description = 'Symlink package assets for development only';

    public function handle()
    {
        $target = base_path('packages/redberry/mailbox-for-laravel/public/vendor/mailbox');
        $link = public_path('vendor/mailbox');

        if (file_exists($link)) {
            $this->error("Target already exists: $link");
            return 1;
        }

        symlink($target, $link);

        $this->info("Linked $link → $target");
        return 0;
    }
}
