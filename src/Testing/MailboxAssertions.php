<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

class MailboxAssertions
{
    public function __construct(
        protected CaptureService $capture,
    ) {}

    /**
     * Assert that at least one (or exactly $expectedCount) captured message(s) match the callback.
     */
    public function assertSent(Closure $callback, ?int $expectedCount = null): void
    {
        $matching = $this->sent($callback);

        if ($expectedCount !== null) {
            PHPUnit::assertCount(
                $expectedCount,
                $matching,
                "Expected [{$expectedCount}] matching message(s), but [{$matching->count()}] were captured. {$this->messagesSummary()}"
            );

            return;
        }

        PHPUnit::assertTrue(
            $matching->isNotEmpty(),
            "No matching message was captured. {$this->messagesSummary()}"
        );
    }

    /**
     * Assert that no captured messages match the callback.
     */
    public function assertNotSent(Closure $callback): void
    {
        $matching = $this->sent($callback);

        PHPUnit::assertTrue(
            $matching->isEmpty(),
            "An unexpected message was captured that matched the given callback. [{$matching->count()}] match(es) found."
        );
    }

    /**
     * Assert that no messages were captured at all.
     */
    public function assertNothingSent(): void
    {
        $all = $this->capture->all();

        PHPUnit::assertEmpty(
            $all,
            'Expected no messages to be captured, but ['.count($all)."] were. {$this->messagesSummary()}"
        );
    }

    /**
     * Assert the total number of captured messages (without filtering).
     */
    public function assertSentCount(int $count): void
    {
        $actual = count($this->capture->all());

        PHPUnit::assertSame(
            $count,
            $actual,
            "Expected [{$count}] message(s) to be captured, but [{$actual}] were. {$this->messagesSummary()}"
        );
    }

    /**
     * Assert that at least one message was sent to the given email address.
     */
    public function assertSentTo(string $email, ?Closure $callback = null): void
    {
        $matching = $this->sentTo($email, $callback);

        PHPUnit::assertTrue(
            $matching->isNotEmpty(),
            "No message was captured to [{$email}]. {$this->messagesSummary()}"
        );
    }

    /**
     * Assert that no messages were sent to the given email address.
     */
    public function assertNotSentTo(string $email, ?Closure $callback = null): void
    {
        $matching = $this->sentTo($email, $callback);

        PHPUnit::assertTrue(
            $matching->isEmpty(),
            "An unexpected message was captured to [{$email}]. [{$matching->count()}] match(es) found."
        );
    }

    /**
     * Get all captured messages, optionally filtered by callback.
     *
     * @return Collection<int, MailboxMessageData>
     */
    public function sent(?Closure $callback = null): Collection
    {
        $messages = Collection::make($this->capture->all());

        if ($callback !== null) {
            $messages = $messages->filter(fn (MailboxMessageData $message): bool => $callback($message));
        }

        return $messages->values();
    }

    /**
     * Get a fluent assertion object for the first matching captured message.
     */
    public function firstSent(?Closure $callback = null): PendingMailboxMessageAssertion
    {
        $messages = $this->sent($callback);

        PHPUnit::assertTrue(
            $messages->isNotEmpty(),
            "No matching message was captured. {$this->messagesSummary()}"
        );

        return new PendingMailboxMessageAssertion($messages->first());
    }

    /**
     * @return Collection<int, MailboxMessageData>
     */
    private function sentTo(string $email, ?Closure $callback = null): Collection
    {
        return $this->sent(function (MailboxMessageData $message) use ($email, $callback): bool {
            $hasRecipient = $this->messageHasRecipient($message, $email);

            if (! $hasRecipient) {
                return false;
            }

            if ($callback !== null) {
                return $callback($message);
            }

            return true;
        });
    }

    private function messageHasRecipient(MailboxMessageData $message, string $email): bool
    {
        foreach ($message->to ?? [] as $recipient) {
            if (strcasecmp($recipient['email'], $email) === 0) {
                return true;
            }
        }

        return false;
    }

    private function messagesSummary(): string
    {
        $all = $this->capture->all();

        if (count($all) === 0) {
            return 'No messages were captured.';
        }

        $subjects = array_map(
            static fn (MailboxMessageData $m): string => $m->subject ?? '(no subject)',
            $all,
        );

        $recipients = [];
        foreach ($all as $message) {
            foreach ($message->to ?? [] as $recipient) {
                $recipients[] = $recipient['email'];
            }
        }

        $recipients = array_unique($recipients);

        return sprintf(
            'Captured %d message(s) with subjects: [%s], to: [%s].',
            count($all),
            implode(', ', $subjects),
            implode(', ', $recipients),
        );
    }
}
