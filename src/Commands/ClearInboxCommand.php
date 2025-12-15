<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\Contracts\MessageStore;

final class ClearInboxCommand extends Command
{
    protected $signature = 'mailbox:clear {--outdated : Clear only messages older than retention period}';

    protected $description = 'Clear stored mailbox messages and attachments';

    public function handle(MessageStore $store): int
    {
        $outdatedOnly = (bool) $this->option('outdated');

        if ($outdatedOnly) {
            return $this->clearOutdated($store);
        }

        return $this->clearAll($store);
    }

    private function clearAll(MessageStore $store): int
    {
        $this->info('Clearing all mailbox messages…');

        $store->clear();

        $this->clearAttachments();

        $this->info('Mailbox fully cleared.');

        return self::SUCCESS;
    }

    private function clearOutdated(MessageStore $store): int
    {
        $seconds = (int) config('mailbox.retention.seconds', 60 * 60 * 24);
        $threshold = Carbon::now()->subSeconds($seconds);

        $this->info('Clearing outdated mailbox messages…');
        $this->line('Retention threshold: '.$threshold->toDateTimeString());

        $store->purgeOlderThan($seconds);

        $this->clearAttachments($threshold);

        $this->info('Outdated mailbox messages cleared.');

        return self::SUCCESS;
    }

    private function clearAttachments(?Carbon $before = null): void
    {
        if (! (bool) config('mailbox.attachments.enabled', true)) {
            return;
        }

        $disk = (string) config('mailbox.attachments.disk', 'mailbox');
        $path = (string) config('mailbox.attachments.path', 'attachments');

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return;
        }

        // Full wipe
        if ($before === null) {
            $storage->deleteDirectory($path);

            return;
        }

        // Outdated only (by last modified time)
        foreach ($storage->allFiles($path) as $file) {
            $lastModified = Carbon::createFromTimestamp($storage->lastModified($file));

            if ($lastModified->lt($before)) {
                $storage->delete($file);
            }
        }
    }
}
