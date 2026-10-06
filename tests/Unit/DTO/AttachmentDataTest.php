<?php

use Redberry\MailboxForLaravel\DTO\AttachmentData;

describe(AttachmentData::class, function () {
    it('keeps the content it is given as raw bytes', function () {
        $attachment = new AttachmentData('a.txt', 'text/plain', 8, 'SGVsbG8=');

        expect($attachment->content)->toBe('SGVsbG8=');
    });

    it('decodes base64 content with fromBase64', function () {
        $attachment = AttachmentData::fromBase64('logo.png', 'image/png', 5, base64_encode("\x89PNG\x00"), 'logo@x', true);

        expect($attachment->content)->toBe("\x89PNG\x00")
            ->and($attachment->filename)->toBe('logo.png')
            ->and($attachment->cid)->toBe('logo@x')
            ->and($attachment->isInline)->toBeTrue();
    });

    it('rejects content that is not valid base64', function () {
        AttachmentData::fromBase64('a.txt', 'text/plain', 1, 'not base64!');
    })->throws(InvalidArgumentException::class, 'Attachment [a.txt] content is not valid base64.');
});
