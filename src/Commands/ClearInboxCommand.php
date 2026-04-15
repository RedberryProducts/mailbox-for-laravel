<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Redberry\MailboxForLaravel\CaptureService;

final class ClearInboxCommand extends Command
{
    protected $signature = 'mailbox:clear {--outdated : Clear only messages older than retention period}';

    protected $description = 'Clear stored mailbox messages and attachments';

    public function handle(CaptureService $service): int
    {
        if ((bool) $this->option('outdated')) {
            return $this->clearOutdated($service);
        }

        return $this->clearAll($service);
    }

    private function clearAll(CaptureService $service): int
    {
        $this->info('Clearing all mailbox messages…');

        $service->clearAll();

        $this->info('Mailbox fully cleared.');

        return self::SUCCESS;
    }

    private function clearOutdated(CaptureService $service): int
    {
        $seconds = (int) config('mailbox.retention', 60 * 60 * 24);
        $threshold = Carbon::now()->subSeconds($seconds);

        $this->info('Clearing outdated mailbox messages…');
        $this->line('Retention threshold: '.$threshold->toDateTimeString());

        $service->purgeOlderThan($seconds);

        $this->info('Outdated mailbox messages cleared.');

        return self::SUCCESS;
    }
}
