<?php

use Redberry\MailboxForLaravel\Support\MessageNormalizer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

describe(MessageNormalizer::class, function () {
    it('normalizes a simple text-only email', function () {
        $email = (new Email())
            ->from('alice@example.com')
            ->to('bob@example.com')
            ->subject('Greetings')
            ->text('just text');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['text'])->toBe('just text')
            ->and($payload['html'])->toBeNull()
            ->and($payload['attachments'])->toBe([]);
    });

    it('normalizes an html-only email', function () {
        $email = (new Email())
            ->from('alice@example.com')
            ->to('bob@example.com')
            ->subject('Hi')
            ->html('<p>hi</p>');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['html'])->toBe('<p>hi</p>')
            ->and($payload['text'])->toBeNull();
    });

    it('normalizes a multipart/alternative email with both text and html', function () {
        $email = (new Email())
            ->from('a@example.com')
            ->to('b@example.com')
            ->subject('Hi')
            ->text('plain')
            ->html('<p>plain</p>');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['text'])->toBe('plain')
            ->and($payload['html'])->toBe('<p>plain</p>');
    });

    it('normalizes unicode headers and encoded words', function () {
        $email = (new Email())
            ->subject('Hello ✌')
            ->from('José <jose@example.com>')
            ->to('r@example.com')
            ->text('Body');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['subject'])->toBe('Hello ✌')
            ->and($payload['from'][0]['name'])->toBe('José');
    });

    it('normalizes multiple recipients in to/cc/bcc', function () {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('a@example.com', 'b@example.com')
            ->cc('c@example.com', 'd@example.com')
            ->bcc('e@example.com', 'f@example.com')
            ->text('body');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['to'])->toHaveCount(2)
            ->and($payload['cc'])->toHaveCount(2)
            ->and($payload['bcc'])->toHaveCount(2);
    });

    it('extracts attachments with metadata and inline flags', function () {
        $email = (new Email())
            ->from('s@example.com')
            ->to('r@example.com')
            ->text('body');

        $inline = (new DataPart('inline', 'img.txt', 'text/plain'))
            ->asInline();
        $inline->setContentId('cid1@example.com');

        $email->attach('file-content', 'doc.txt', 'text/plain');
        $email->addPart($inline);

        $payload = MessageNormalizer::normalize($email, storeAttachmentsInline: true);

        expect($payload['attachments'])->toHaveCount(2);

        $first = $payload['attachments'][0];
        expect($first['filename'])->toBe('doc.txt')
            ->and($first['contentType'])->toContain('text/plain')
            ->and($first['inline'])->toBeFalse()
            ->and($first['disposition'])->toContain('attachment')
            ->and($first['size'])->toBe(strlen('file-content'))
            ->and($first['content'])->toBe(base64_encode('file-content'));

        $second = $payload['attachments'][1];
        expect($second['contentId'])->toBe('cid1@example.com')
            ->and($second['inline'])->toBeTrue()
            ->and($second['disposition'])->toContain('inline');
    });

    it('preserves content-id mapping for inline images', function () {
        $email = (new Email())
            ->from('a@example.com')
            ->to('b@example.com')
            ->text('body');

        $part = (new DataPart('img', 'image.png', 'image/png'))
            ->asInline();
        $part->setContentId('img1@example.com');
        $email->addPart($part);

        $payload = MessageNormalizer::normalize($email);

        expect($payload['attachments'][0]['contentId'])->toBe('img1@example.com');
    });

    it('parses Date header and falls back to current time if missing', function () {
        $email = (new Email())
            ->from('a@example.com')
            ->to('b@example.com')
            ->text('body');
        $email->getHeaders()->addDateHeader('Date', new DateTimeImmutable('2000-12-21 16:01:07 +0200'));

        $payload = MessageNormalizer::normalize($email);
        expect($payload['date'])->toBe('Thu, 21 Dec 2000 16:01:07 +0200');

        $emailNoDate = (new Email())
            ->from('a@example.com')
            ->to('b@example.com')
            ->text('body');
        $payload2 = MessageNormalizer::normalize($emailNoDate);
        expect($payload2['date'])->toBeNull();
    });

    it('handles empty subject and no sender gracefully', function () {
        $email = (new Email())
            ->to('x@example.com')
            ->text('body');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['subject'])->toBeNull()
            ->and($payload['from'])->toBe([])
            ->and($payload['sender'])->toBeNull();
    });

    it('enforces a stable schema version and includes saved_at timestamp', function () {
        $email = (new Email())
            ->from('a@example.com')
            ->to('b@example.com')
            ->text('body');

        $payload = MessageNormalizer::normalize($email);

        expect($payload['version'])->toBe(1)
            ->and(strtotime($payload['saved_at']))->toBeInt();
    });
});
