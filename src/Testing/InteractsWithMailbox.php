<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Testing;

use Redberry\MailboxForLaravel\CaptureService;

/**
 * Trait for test classes that need to assert against captured mailbox messages.
 *
 * Automatically clears the mailbox before each test for isolation.
 * Works with both PHPUnit TestCase and Pest (via uses()).
 *
 * Requires a Laravel application context (Orchestra Testbench or Laravel's TestCase).
 */
trait InteractsWithMailbox
{
    protected ?MailboxAssertions $mailboxAssertions = null;

    /**
     * Invoked automatically by Laravel's setUpTraits() via the
     * setUp{TraitName} convention. Registers an application-boot hook that
     * clears the mailbox once the test application is ready.
     */
    protected function setUpInteractsWithMailbox(): void
    {
        $this->afterApplicationCreated(function (): void {
            $this->clearMailbox();
        });
    }

    /**
     * Invoked automatically by Laravel's setUpTraits() via the
     * tearDown{TraitName} convention. Clears the cached assertions instance
     * between tests so state from one test cannot leak into another.
     */
    protected function tearDownInteractsWithMailbox(): void
    {
        $this->mailboxAssertions = null;
    }

    /**
     * Clear all captured messages from the mailbox.
     */
    public function clearMailbox(): void
    {
        app(CaptureService::class)->clearAll();
    }

    /**
     * Get the mailbox assertions instance for the current test.
     */
    public function mailbox(): MailboxAssertions
    {
        if ($this->mailboxAssertions === null) {
            $this->mailboxAssertions = new MailboxAssertions(
                app(CaptureService::class),
            );
        }

        return $this->mailboxAssertions;
    }
}
