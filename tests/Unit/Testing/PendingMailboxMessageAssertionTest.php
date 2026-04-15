<?php

use PHPUnit\Framework\AssertionFailedError;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;
use Redberry\MailboxForLaravel\Testing\PendingMailboxMessageAssertion;

function messageAssertion(array $overrides = []): PendingMailboxMessageAssertion
{
    $defaults = [
        'id' => 'test-1',
        'subject' => 'Welcome to Our App',
        'from' => [['email' => 'noreply@app.com', 'name' => 'App']],
        'to' => [['email' => 'user@example.com', 'name' => 'John Doe']],
        'cc' => [['email' => 'cc@example.com']],
        'bcc' => [['email' => 'bcc@example.com']],
        'reply_to' => [['email' => 'support@app.com']],
        'html' => '<h1>Welcome</h1><p>Hello John, welcome to our platform!</p>',
        'text' => 'Welcome! Hello John, welcome to our platform!',
        'headers' => [
            'MIME-Version' => ['1.0'],
            'X-Custom' => ['custom-value'],
        ],
        'attachments' => [
            ['filename' => 'guide.pdf', 'contentType' => 'application/pdf', 'size' => 1024],
            ['filename' => 'logo.png', 'contentType' => 'image/png; charset=binary', 'size' => 512, 'contentId' => 'logo123', 'inline' => true],
        ],
    ];

    return new PendingMailboxMessageAssertion(
        MailboxMessageData::from(array_merge($defaults, $overrides)),
    );
}

describe(PendingMailboxMessageAssertion::class, function () {
    describe('recipient assertions', function () {
        it('asserts from email', function () {
            messageAssertion()->assertFrom('noreply@app.com');
        });

        it('asserts from email with name', function () {
            messageAssertion()->assertFrom('noreply@app.com', 'App');
        });

        it('asserts from is case-insensitive', function () {
            messageAssertion()->assertFrom('NoReply@App.com');
        });

        it('fails when from email does not match', function () {
            messageAssertion()->assertFrom('wrong@app.com');
        })->throws(AssertionFailedError::class);

        it('asserts has to recipient', function () {
            messageAssertion()->assertHasTo('user@example.com');
        });

        it('asserts has to with name', function () {
            messageAssertion()->assertHasTo('user@example.com', 'John Doe');
        });

        it('fails when to recipient name does not match', function () {
            messageAssertion()->assertHasTo('user@example.com', 'Wrong Name');
        })->throws(AssertionFailedError::class);

        it('asserts has cc recipient', function () {
            messageAssertion()->assertHasCc('cc@example.com');
        });

        it('fails when cc recipient does not exist', function () {
            messageAssertion()->assertHasCc('not-cc@example.com');
        })->throws(AssertionFailedError::class);

        it('asserts has bcc recipient', function () {
            messageAssertion()->assertHasBcc('bcc@example.com');
        });

        it('asserts has reply-to', function () {
            messageAssertion()->assertHasReplyTo('support@app.com');
        });

        it('fails when recipients are null', function () {
            messageAssertion(['to' => null])->assertHasTo('user@example.com');
        })->throws(AssertionFailedError::class);
    });

    describe('subject assertions', function () {
        it('asserts exact subject', function () {
            messageAssertion()->assertHasSubject('Welcome to Our App');
        });

        it('fails on subject mismatch', function () {
            messageAssertion()->assertHasSubject('Wrong Subject');
        })->throws(AssertionFailedError::class);

        it('asserts subject contains substring', function () {
            messageAssertion()->assertSubjectContains('Welcome');
        });

        it('fails when subject does not contain substring', function () {
            messageAssertion()->assertSubjectContains('Goodbye');
        })->throws(AssertionFailedError::class);

        it('fails when subject is null and assertSubjectContains called', function () {
            messageAssertion(['subject' => null])->assertSubjectContains('anything');
        })->throws(AssertionFailedError::class);
    });

    describe('html body assertions', function () {
        it('asserts see in html', function () {
            messageAssertion()->assertSeeInHtml('<h1>Welcome</h1>');
        });

        it('fails when string not in html', function () {
            messageAssertion()->assertSeeInHtml('Not Present');
        })->throws(AssertionFailedError::class);

        it('asserts dont see in html', function () {
            messageAssertion()->assertDontSeeInHtml('Not Present');
        });

        it('fails when unexpected string in html', function () {
            messageAssertion()->assertDontSeeInHtml('Welcome');
        })->throws(AssertionFailedError::class);

        it('passes dont see in html when html is null', function () {
            $result = messageAssertion(['html' => null])->assertDontSeeInHtml('anything');

            expect($result)->toBeInstanceOf(PendingMailboxMessageAssertion::class);
        });

        it('fails see in html when html is null', function () {
            messageAssertion(['html' => null])->assertSeeInHtml('anything');
        })->throws(AssertionFailedError::class);

        it('asserts see in order in html', function () {
            messageAssertion()->assertSeeInOrderInHtml(['<h1>Welcome</h1>', 'Hello John']);
        });

        it('fails when order is wrong in html', function () {
            messageAssertion()->assertSeeInOrderInHtml(['Hello John', '<h1>Welcome</h1>']);
        })->throws(AssertionFailedError::class);
    });

    describe('text body assertions', function () {
        it('asserts see in text', function () {
            messageAssertion()->assertSeeInText('Hello John');
        });

        it('fails when string not in text', function () {
            messageAssertion()->assertSeeInText('Not Present');
        })->throws(AssertionFailedError::class);

        it('asserts dont see in text', function () {
            messageAssertion()->assertDontSeeInText('Not Present');
        });

        it('passes dont see in text when text is null', function () {
            $result = messageAssertion(['text' => null])->assertDontSeeInText('anything');

            expect($result)->toBeInstanceOf(PendingMailboxMessageAssertion::class);
        });

        it('asserts see in order in text', function () {
            messageAssertion()->assertSeeInOrderInText(['Welcome!', 'Hello John']);
        });
    });

    describe('attachment assertions', function () {
        it('asserts has attachment by filename', function () {
            messageAssertion()->assertHasAttachment('guide.pdf');
        });

        it('asserts has attachment by filename and mime type', function () {
            messageAssertion()->assertHasAttachment('guide.pdf', 'application/pdf');
        });

        it('strips mime type parameters when matching', function () {
            messageAssertion()->assertHasAttachment('logo.png', 'image/png');
        });

        it('fails when attachment not found', function () {
            messageAssertion()->assertHasAttachment('missing.pdf');
        })->throws(AssertionFailedError::class);

        it('fails when mime type does not match', function () {
            messageAssertion()->assertHasAttachment('guide.pdf', 'image/png');
        })->throws(AssertionFailedError::class);

        it('asserts has no attachments', function () {
            messageAssertion(['attachments' => []])->assertHasNoAttachments();
        });

        it('asserts has no attachments when null', function () {
            messageAssertion(['attachments' => null])->assertHasNoAttachments();
        });

        it('fails has no attachments when attachments exist', function () {
            messageAssertion()->assertHasNoAttachments();
        })->throws(AssertionFailedError::class);

        it('asserts attachment count', function () {
            messageAssertion()->assertAttachmentCount(2);
        });

        it('fails when attachment count does not match', function () {
            messageAssertion()->assertAttachmentCount(5);
        })->throws(AssertionFailedError::class);

        it('handles mimeType key as fallback for contentType', function () {
            $assertion = messageAssertion([
                'attachments' => [
                    ['filename' => 'doc.txt', 'mimeType' => 'text/plain', 'size' => 100],
                ],
            ]);

            $assertion->assertHasAttachment('doc.txt', 'text/plain');
        });
    });

    describe('header assertions', function () {
        it('asserts has header by name', function () {
            messageAssertion()->assertHasHeader('MIME-Version');
        });

        it('asserts has header with value', function () {
            messageAssertion()->assertHasHeader('MIME-Version', '1.0');
        });

        it('fails when header not found', function () {
            messageAssertion()->assertHasHeader('X-Missing');
        })->throws(AssertionFailedError::class);

        it('fails when header value does not match', function () {
            messageAssertion()->assertHasHeader('MIME-Version', '2.0');
        })->throws(AssertionFailedError::class);
    });

    describe('chaining', function () {
        it('supports fluent chaining', function () {
            $result = messageAssertion()
                ->assertFrom('noreply@app.com')
                ->assertHasTo('user@example.com')
                ->assertHasSubject('Welcome to Our App')
                ->assertSeeInHtml('Welcome')
                ->assertSeeInText('Hello John')
                ->assertHasAttachment('guide.pdf')
                ->assertAttachmentCount(2);

            expect($result)->toBeInstanceOf(PendingMailboxMessageAssertion::class);
        });

        it('exposes the underlying message via getMessage', function () {
            $assertion = messageAssertion();

            expect($assertion->getMessage())->toBeInstanceOf(MailboxMessageData::class);
            expect($assertion->getMessage()->id)->toBe('test-1');
        });
    });
});
