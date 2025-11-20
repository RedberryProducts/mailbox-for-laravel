<?php

use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Redberry\MailboxForLaravel\Support\MessageNormalizer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

describe('MessageNormalizer::extractAttachments', function () {
    it('extracts regular attachments with metadata', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test')
            ->text('body');

        $email->attach('file-content-here', 'document.txt', 'text/plain');

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments)->toHaveCount(1)
            ->and($attachments[0])->toBeInstanceOf(AttachmentData::class)
            ->and($attachments[0]->filename)->toBe('document.txt')
            ->and($attachments[0]->mimeType)->toBe('text/plain')
            ->and($attachments[0]->size)->toBe(strlen('file-content-here'))
            ->and($attachments[0]->isInline)->toBeFalse()
            ->and($attachments[0]->cid)->toBeNull()
            ->and($attachments[0]->content)->toBe(base64_encode('file-content-here'));
    });

    it('extracts inline attachments with CID', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('body');

        $inline = (new DataPart('image-data', 'image.png', 'image/png'))
            ->asInline();
        $inline->setContentId('img123@example.com');
        $email->addPart($inline);

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments)->toHaveCount(1)
            ->and($attachments[0]->filename)->toBe('image.png')
            ->and($attachments[0]->mimeType)->toBe('image/png')
            ->and($attachments[0]->isInline)->toBeTrue()
            ->and($attachments[0]->cid)->toBe('img123@example.com')
            ->and($attachments[0]->content)->toBe(base64_encode('image-data'));
    });

    it('extracts multiple attachments', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('body');

        $email->attach('doc1', 'file1.txt', 'text/plain');
        $email->attach('doc2', 'file2.pdf', 'application/pdf');
        $email->attach('doc3', 'file3.jpg', 'image/jpeg');

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments)->toHaveCount(3)
            ->and($attachments[0]->filename)->toBe('file1.txt')
            ->and($attachments[1]->filename)->toBe('file2.pdf')
            ->and($attachments[2]->filename)->toBe('file3.jpg');
    });

    it('returns empty array when no attachments', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('body');

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments)->toBe([]);
    });

    it('handles attachments without explicit filename', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('body');

        $part = new DataPart('content', null, 'application/octet-stream');
        $email->addPart($part);

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments)->toHaveCount(1)
            ->and($attachments[0]->filename)->toBe('unnamed');
    });

    it('extracts mime type correctly from Content-Type header', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('body');

        $email->attach('data', 'file.pdf', 'application/pdf; name="file.pdf"');

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments[0]->mimeType)->toBe('application/pdf');
    });

    it('handles both regular and inline attachments together', function () {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('body');

        $email->attach('regular-file', 'document.txt', 'text/plain');

        $inline = (new DataPart('inline-image', 'logo.png', 'image/png'))
            ->asInline();
        $inline->setContentId('logo@example.com');
        $email->addPart($inline);

        $attachments = MessageNormalizer::extractAttachments($email);

        expect($attachments)->toHaveCount(2)
            ->and($attachments[0]->isInline)->toBeFalse()
            ->and($attachments[1]->isInline)->toBeTrue()
            ->and($attachments[1]->cid)->toBe('logo@example.com');
    });
});
