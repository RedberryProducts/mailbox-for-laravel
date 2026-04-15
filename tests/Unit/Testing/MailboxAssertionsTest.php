<?php

use PHPUnit\Framework\AssertionFailedError;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\Testing\MailboxAssertions;
use Redberry\MailboxForLaravel\Testing\PendingMailboxMessageAssertion;

function assertions(): MailboxAssertions
{
    $path = sys_get_temp_dir().'/mailbox-assertions-tests-'.uniqid();
    $store = new FileStorage($path);
    $capture = new CaptureService($store);

    return new MailboxAssertions($capture);
}

function assertionsWithMessages(array $messages): MailboxAssertions
{
    $path = sys_get_temp_dir().'/mailbox-assertions-tests-'.uniqid();
    $store = new FileStorage($path);
    $capture = new CaptureService($store);

    foreach ($messages as $message) {
        $capture->store($message);
    }

    return new MailboxAssertions($capture);
}

describe(MailboxAssertions::class, function () {
    describe('assertNothingSent', function () {
        it('passes when no messages captured', function () {
            assertions()->assertNothingSent();
        });

        it('fails when messages exist', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'user@test.com']]],
            ])->assertNothingSent();
        })->throws(AssertionFailedError::class);
    });

    describe('assertSentCount', function () {
        it('passes when count matches', function () {
            assertionsWithMessages([
                ['subject' => 'One'],
                ['subject' => 'Two'],
            ])->assertSentCount(2);
        });

        it('fails when count does not match', function () {
            assertionsWithMessages([
                ['subject' => 'One'],
            ])->assertSentCount(5);
        })->throws(AssertionFailedError::class);

        it('passes for zero when empty', function () {
            assertions()->assertSentCount(0);
        });
    });

    describe('assertSent', function () {
        it('passes when callback matches a message', function () {
            assertionsWithMessages([
                ['subject' => 'Welcome', 'to' => [['email' => 'user@test.com']]],
            ])->assertSent(fn (MailboxMessageData $m) => $m->subject === 'Welcome');
        });

        it('fails when no messages match callback', function () {
            assertionsWithMessages([
                ['subject' => 'Welcome'],
            ])->assertSent(fn (MailboxMessageData $m) => $m->subject === 'Goodbye');
        })->throws(AssertionFailedError::class);

        it('passes when exact count matches', function () {
            assertionsWithMessages([
                ['subject' => 'Newsletter', 'timestamp' => 1000],
                ['subject' => 'Newsletter', 'timestamp' => 2000],
                ['subject' => 'Other'],
            ])->assertSent(
                fn (MailboxMessageData $m) => $m->subject === 'Newsletter',
                expectedCount: 2
            );
        });

        it('fails when count does not match', function () {
            assertionsWithMessages([
                ['subject' => 'Newsletter'],
            ])->assertSent(
                fn (MailboxMessageData $m) => $m->subject === 'Newsletter',
                expectedCount: 3
            );
        })->throws(AssertionFailedError::class);

        it('fails when no messages captured', function () {
            assertions()->assertSent(fn (MailboxMessageData $m) => true);
        })->throws(AssertionFailedError::class);
    });

    describe('assertNotSent', function () {
        it('passes when no messages match', function () {
            assertionsWithMessages([
                ['subject' => 'Welcome'],
            ])->assertNotSent(fn (MailboxMessageData $m) => $m->subject === 'Goodbye');
        });

        it('passes when no messages exist', function () {
            assertions()->assertNotSent(fn (MailboxMessageData $m) => true);
        });

        it('fails when a message matches', function () {
            assertionsWithMessages([
                ['subject' => 'Welcome'],
            ])->assertNotSent(fn (MailboxMessageData $m) => $m->subject === 'Welcome');
        })->throws(AssertionFailedError::class);
    });

    describe('assertSentTo', function () {
        it('passes when message sent to email', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'user@test.com']]],
            ])->assertSentTo('user@test.com');
        });

        it('is case-insensitive', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'User@Test.com']]],
            ])->assertSentTo('user@test.com');
        });

        it('passes with additional callback', function () {
            assertionsWithMessages([
                ['subject' => 'Welcome', 'to' => [['email' => 'user@test.com']]],
                ['subject' => 'Reset', 'to' => [['email' => 'user@test.com']]],
            ])->assertSentTo('user@test.com', fn (MailboxMessageData $m) => $m->subject === 'Welcome');
        });

        it('fails when no message sent to email', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'other@test.com']]],
            ])->assertSentTo('user@test.com');
        })->throws(AssertionFailedError::class);

        it('fails when callback does not match', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'user@test.com']]],
            ])->assertSentTo('user@test.com', fn (MailboxMessageData $m) => $m->subject === 'Goodbye');
        })->throws(AssertionFailedError::class);
    });

    describe('assertNotSentTo', function () {
        it('passes when no message sent to email', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'other@test.com']]],
            ])->assertNotSentTo('user@test.com');
        });

        it('fails when message sent to email', function () {
            assertionsWithMessages([
                ['subject' => 'Hello', 'to' => [['email' => 'user@test.com']]],
            ])->assertNotSentTo('user@test.com');
        })->throws(AssertionFailedError::class);
    });

    describe('sent', function () {
        it('returns all messages without callback', function () {
            $a = assertionsWithMessages([
                ['subject' => 'One', 'timestamp' => 1000],
                ['subject' => 'Two', 'timestamp' => 2000],
            ]);

            $result = $a->sent();

            expect($result)->toHaveCount(2);
            expect($result->first())->toBeInstanceOf(MailboxMessageData::class);
        });

        it('returns filtered messages with callback', function () {
            $a = assertionsWithMessages([
                ['subject' => 'Newsletter', 'timestamp' => 1000],
                ['subject' => 'Welcome', 'timestamp' => 2000],
                ['subject' => 'Newsletter', 'timestamp' => 3000],
            ]);

            $result = $a->sent(fn (MailboxMessageData $m) => $m->subject === 'Newsletter');

            expect($result)->toHaveCount(2);
        });

        it('returns empty collection when no messages', function () {
            $result = assertions()->sent();

            expect($result)->toBeEmpty();
        });
    });

    describe('firstSent', function () {
        it('returns PendingMailboxMessageAssertion for first message', function () {
            $a = assertionsWithMessages([
                ['subject' => 'First', 'timestamp' => 2000],
                ['subject' => 'Second', 'timestamp' => 1000],
            ]);

            $result = $a->firstSent();

            expect($result)->toBeInstanceOf(PendingMailboxMessageAssertion::class);
            expect($result->getMessage()->subject)->toBe('First');
        });

        it('returns first matching when callback provided', function () {
            $a = assertionsWithMessages([
                ['subject' => 'Other', 'timestamp' => 3000],
                ['subject' => 'Target', 'timestamp' => 2000],
                ['subject' => 'Target', 'timestamp' => 1000],
            ]);

            $result = $a->firstSent(fn (MailboxMessageData $m) => $m->subject === 'Target');

            expect($result->getMessage()->subject)->toBe('Target');
        });

        it('fails when no messages match', function () {
            assertions()->firstSent();
        })->throws(AssertionFailedError::class);

        it('allows chaining per-message assertions', function () {
            $a = assertionsWithMessages([
                [
                    'subject' => 'Welcome',
                    'from' => [['email' => 'app@test.com']],
                    'to' => [['email' => 'user@test.com']],
                    'html' => '<p>Hello World</p>',
                ],
            ]);

            $a->firstSent()
                ->assertHasSubject('Welcome')
                ->assertFrom('app@test.com')
                ->assertHasTo('user@test.com')
                ->assertSeeInHtml('Hello World');
        });
    });
});
