<?php

namespace Redberry\MailboxForLaravel\Facades;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Testing\MailboxAssertions;
use Redberry\MailboxForLaravel\Testing\PendingMailboxMessageAssertion;

/**
 * @method static string store(array $payload)
 * @method static string storeRaw(string $raw)
 * @method static \Redberry\MailboxForLaravel\DTO\PaginatedMessages list(int $page = 1, int $perPage = 10, ?string $search = null)
 * @method static array<int, MailboxMessageData> all()
 * @method static MailboxMessageData|null find(string $id)
 * @method static MailboxMessageData|null update(string $id, array $changes)
 * @method static MailboxMessageData|null markSeen(string $id)
 * @method static void delete(string $id)
 * @method static void purgeOlderThan(int $seconds)
 * @method static void clearAll()
 * @method static void assertSent(Closure $callback, ?int $expectedCount = null)
 * @method static void assertNotSent(Closure $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertSentTo(string $email, ?Closure $callback = null)
 * @method static void assertNotSentTo(string $email, ?Closure $callback = null)
 * @method static Collection sent(?Closure $callback = null)
 * @method static PendingMailboxMessageAssertion firstSent(?Closure $callback = null)
 *
 * @see CaptureService
 * @see MailboxAssertions
 */
class Mailbox extends Facade
{
    private static array $assertionMethods = [
        'assertSent',
        'assertNotSent',
        'assertNothingSent',
        'assertSentCount',
        'assertSentTo',
        'assertNotSentTo',
        'sent',
        'firstSent',
    ];

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $args
     */
    public static function __callStatic($method, $args): mixed
    {
        if (in_array($method, self::$assertionMethods, true)) {
            $assertions = new MailboxAssertions(static::getFacadeRoot());

            return $assertions->{$method}(...$args);
        }

        return parent::__callStatic($method, $args);
    }

    protected static function getFacadeAccessor(): string
    {
        return CaptureService::class;
    }
}
