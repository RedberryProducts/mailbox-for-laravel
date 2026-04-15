<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Testing;

use PHPUnit\Framework\Assert as PHPUnit;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

class PendingMailboxMessageAssertion
{
    public function __construct(
        protected MailboxMessageData $message,
    ) {}

    public function getMessage(): MailboxMessageData
    {
        return $this->message;
    }

    public function assertFrom(string $email, ?string $name = null): self
    {
        PHPUnit::assertTrue(
            $this->hasRecipientIn($this->message->from, $email, $name),
            "Expected message to be from [{$email}], but it was from [{$this->formatRecipients($this->message->from)}]."
        );

        return $this;
    }

    public function assertHasTo(string $email, ?string $name = null): self
    {
        PHPUnit::assertTrue(
            $this->hasRecipientIn($this->message->to, $email, $name),
            "Expected message to have [{$email}] as a 'to' recipient, but recipients were [{$this->formatRecipients($this->message->to)}]."
        );

        return $this;
    }

    public function assertHasCc(string $email, ?string $name = null): self
    {
        PHPUnit::assertTrue(
            $this->hasRecipientIn($this->message->cc, $email, $name),
            "Expected message to have [{$email}] as a 'cc' recipient, but cc recipients were [{$this->formatRecipients($this->message->cc)}]."
        );

        return $this;
    }

    public function assertHasBcc(string $email, ?string $name = null): self
    {
        PHPUnit::assertTrue(
            $this->hasRecipientIn($this->message->bcc, $email, $name),
            "Expected message to have [{$email}] as a 'bcc' recipient, but bcc recipients were [{$this->formatRecipients($this->message->bcc)}]."
        );

        return $this;
    }

    public function assertHasReplyTo(string $email, ?string $name = null): self
    {
        PHPUnit::assertTrue(
            $this->hasRecipientIn($this->message->reply_to, $email, $name),
            "Expected message to have [{$email}] as a 'reply-to' address, but reply-to addresses were [{$this->formatRecipients($this->message->reply_to)}]."
        );

        return $this;
    }

    public function assertHasSubject(string $subject): self
    {
        PHPUnit::assertSame(
            $subject,
            $this->message->subject,
            "Expected message subject to be [{$subject}], but got [{$this->message->subject}]."
        );

        return $this;
    }

    public function assertSubjectContains(string $substring): self
    {
        PHPUnit::assertNotNull($this->message->subject, 'Expected message to have a subject, but subject was null.');

        PHPUnit::assertStringContainsString(
            $substring,
            $this->message->subject,
            "Expected message subject to contain [{$substring}], but subject was [{$this->message->subject}]."
        );

        return $this;
    }

    public function assertSeeInHtml(string $string): self
    {
        PHPUnit::assertNotNull($this->message->html, 'Expected message to have an HTML body, but it was null.');

        PHPUnit::assertStringContainsString(
            $string,
            $this->message->html,
            "Expected to see [{$string}] in the HTML body, but it was not found."
        );

        return $this;
    }

    public function assertDontSeeInHtml(string $string): self
    {
        if ($this->message->html === null) {
            return $this;
        }

        PHPUnit::assertStringNotContainsString(
            $string,
            $this->message->html,
            "Unexpected [{$string}] was found in the HTML body."
        );

        return $this;
    }

    public function assertSeeInText(string $string): self
    {
        PHPUnit::assertNotNull($this->message->text, 'Expected message to have a text body, but it was null.');

        PHPUnit::assertStringContainsString(
            $string,
            $this->message->text,
            "Expected to see [{$string}] in the text body, but it was not found."
        );

        return $this;
    }

    public function assertDontSeeInText(string $string): self
    {
        if ($this->message->text === null) {
            return $this;
        }

        PHPUnit::assertStringNotContainsString(
            $string,
            $this->message->text,
            "Unexpected [{$string}] was found in the text body."
        );

        return $this;
    }

    /**
     * @param  array<int, string>  $strings
     */
    public function assertSeeInOrderInHtml(array $strings): self
    {
        PHPUnit::assertNotNull($this->message->html, 'Expected message to have an HTML body, but it was null.');

        $this->assertStringsInOrder($this->message->html, $strings, 'HTML');

        return $this;
    }

    /**
     * @param  array<int, string>  $strings
     */
    public function assertSeeInOrderInText(array $strings): self
    {
        PHPUnit::assertNotNull($this->message->text, 'Expected message to have a text body, but it was null.');

        $this->assertStringsInOrder($this->message->text, $strings, 'text');

        return $this;
    }

    public function assertHasAttachment(string $filename, ?string $mimeType = null): self
    {
        $attachments = $this->message->attachments ?? [];

        $found = false;

        foreach ($attachments as $attachment) {
            $attachmentFilename = $attachment['filename'] ?? null;

            if ($attachmentFilename !== $filename) {
                continue;
            }

            if ($mimeType !== null) {
                $attachmentMimeType = $attachment['contentType'] ?? $attachment['mimeType'] ?? null;

                if ($attachmentMimeType === null) {
                    continue;
                }

                // Strip parameters (e.g., "text/plain; charset=utf-8" → "text/plain")
                $attachmentMimeType = explode(';', $attachmentMimeType)[0];

                if (strcasecmp($attachmentMimeType, $mimeType) !== 0) {
                    continue;
                }
            }

            $found = true;

            break;
        }

        PHPUnit::assertTrue(
            $found,
            "Expected attachment [{$filename}]".($mimeType ? " with type [{$mimeType}]" : '').' was not found on the message.'
        );

        return $this;
    }

    public function assertHasNoAttachments(): self
    {
        $count = count($this->message->attachments ?? []);

        PHPUnit::assertSame(
            0,
            $count,
            "Expected message to have no attachments, but found [{$count}]."
        );

        return $this;
    }

    public function assertAttachmentCount(int $count): self
    {
        $actual = count($this->message->attachments ?? []);

        PHPUnit::assertSame(
            $count,
            $actual,
            "Expected message to have [{$count}] attachment(s), but found [{$actual}]."
        );

        return $this;
    }

    public function assertHasHeader(string $name, ?string $value = null): self
    {
        $headers = $this->message->headers ?? [];

        PHPUnit::assertArrayHasKey(
            $name,
            $headers,
            "Expected message to have header [{$name}], but it was not found."
        );

        if ($value !== null) {
            $headerValue = $headers[$name];

            // Headers can be stored as arrays of values
            if (is_array($headerValue)) {
                PHPUnit::assertContains(
                    $value,
                    $headerValue,
                    "Expected header [{$name}] to contain value [{$value}]."
                );
            } else {
                PHPUnit::assertSame(
                    $value,
                    $headerValue,
                    "Expected header [{$name}] to be [{$value}], but got [{$headerValue}]."
                );
            }
        }

        return $this;
    }

    /**
     * @param  array<int, array{email:string, name?:string}>|null  $recipients
     */
    private function hasRecipientIn(?array $recipients, string $email, ?string $name): bool
    {
        if ($recipients === null) {
            return false;
        }

        foreach ($recipients as $recipient) {
            if (strcasecmp($recipient['email'], $email) !== 0) {
                continue;
            }

            if ($name !== null && ($recipient['name'] ?? '') !== $name) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<int, array{email:string, name?:string}>|null  $recipients
     */
    private function formatRecipients(?array $recipients): string
    {
        if ($recipients === null || count($recipients) === 0) {
            return '(none)';
        }

        return implode(', ', array_map(
            static fn (array $r): string => $r['email'],
            $recipients,
        ));
    }

    /**
     * @param  array<int, string>  $strings
     */
    private function assertStringsInOrder(string $content, array $strings, string $bodyType): void
    {
        $position = 0;

        foreach ($strings as $index => $string) {
            $found = strpos($content, $string, $position);

            PHPUnit::assertNotFalse(
                $found,
                "Expected to see [{$string}] at position [{$index}] in the {$bodyType} body, but it was not found after position [{$position}]."
            );

            $position = $found + strlen($string);
        }
    }
}
