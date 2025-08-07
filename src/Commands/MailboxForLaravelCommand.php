<?php

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;

class MailboxForLaravelCommand extends Command
{
    public $signature = 'mailbox-for-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
