<?php

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;
use Redberry\MailboxForLaravel\Testing\MailboxAssertions;
use Redberry\MailboxForLaravel\Testing\PendingMailboxMessageAssertion;

uses(InteractsWithMailbox::class);

class TestMailable extends Mailable
{
    public function __construct(
        public string $greeting = 'Hello World',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Email',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<html><body><h1>{$this->greeting}</h1><p>This is a test email.</p></body></html>",
        );
    }
}

describe('InteractsWithMailbox trait', function () {
    it('starts with empty mailbox', function () {
        $this->mailbox()->assertNothingSent();
    });

    it('returns MailboxAssertions instance', function () {
        expect($this->mailbox())->toBeInstanceOf(MailboxAssertions::class);
    });

    it('caches the assertions instance', function () {
        $first = $this->mailbox();
        $second = $this->mailbox();

        expect($first)->toBe($second);
    });

    it('can clear mailbox manually', function () {
        app(CaptureService::class)->store(['subject' => 'Test']);

        $this->clearMailbox();

        $this->mailbox()->assertNothingSent();
    });
});

describe('InteractsWithMailbox with real emails', function () {
    it('captures sent email and asserts count', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        $this->mailbox()->assertSentCount(1);
    });

    it('asserts sent to specific recipient', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        $this->mailbox()->assertSentTo('user@test.com');
    });

    it('asserts not sent to another recipient', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        $this->mailbox()->assertNotSentTo('other@test.com');
    });

    it('asserts message content via firstSent', function () {
        Mail::to('user@test.com')->send(new TestMailable('Welcome!'));

        $this->mailbox()->firstSent()
            ->assertHasSubject('Test Email')
            ->assertHasTo('user@test.com')
            ->assertSeeInHtml('Welcome!')
            ->assertSeeInHtml('This is a test email.');
    });

    it('filters messages with assertSent callback', function () {
        Mail::to('user@test.com')->send(new TestMailable('First'));
        Mail::to('other@test.com')->send(new TestMailable('Second'));

        $this->mailbox()->assertSentCount(2);
        $this->mailbox()->assertSent(
            fn (MailboxMessageData $m) => $m->subject === 'Test Email',
            expectedCount: 2
        );
    });

    it('isolates tests - mailbox is cleared between tests', function () {
        // This test runs after previous ones that sent emails.
        // If isolation works, the mailbox should be empty.
        $this->mailbox()->assertNothingSent();

        // Send one email
        Mail::to('isolated@test.com')->send(new TestMailable);

        $this->mailbox()->assertSentCount(1);
    });
});

describe('Mailbox facade assertions', function () {
    it('supports assertNothingSent on facade', function () {
        Mailbox::assertNothingSent();
    });

    it('supports assertSentCount on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        Mailbox::assertSentCount(1);
    });

    it('supports assertSentTo on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        Mailbox::assertSentTo('user@test.com');
    });

    it('supports assertNotSentTo on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        Mailbox::assertNotSentTo('other@test.com');
    });

    it('supports assertSent with callback on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        Mailbox::assertSent(fn (MailboxMessageData $m) => $m->subject === 'Test Email');
    });

    it('supports assertNotSent on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        Mailbox::assertNotSent(fn (MailboxMessageData $m) => $m->subject === 'Non-existent');
    });

    it('supports sent() query on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        $messages = Mailbox::sent();

        expect($messages)->toHaveCount(1);
        expect($messages->first())->toBeInstanceOf(MailboxMessageData::class);
    });

    it('supports firstSent() on facade', function () {
        Mail::to('user@test.com')->send(new TestMailable);

        $assertion = Mailbox::firstSent();

        expect($assertion)->toBeInstanceOf(PendingMailboxMessageAssertion::class);
        $assertion->assertHasSubject('Test Email');
    });

    it('still proxies normal CaptureService methods', function () {
        $all = Mailbox::all();

        expect($all)->toBeArray();
        expect($all)->toBeEmpty();
    });
});
