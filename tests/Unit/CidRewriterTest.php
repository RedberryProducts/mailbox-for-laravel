<?php

use Illuminate\Support\Facades\Storage;
use Redberry\MailboxForLaravel\Models\MailboxAttachment;
use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Storage\AttachmentStore;
use Redberry\MailboxForLaravel\Support\CidRewriter;

beforeEach(function () {
    config(['mailbox.store.database.connection' => 'testing']);
    $this->artisan('migrate', ['--database' => 'testing'])->run();

    Storage::fake('mailbox');
});

describe(CidRewriter::class, function () {
    it('rewrites CID references in HTML to inline URLs', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att123',
            'message_id' => $message->id,
            'filename' => 'logo.png',
            'mime_type' => 'image/png',
            'size' => 1000,
            'disk' => 'mailbox',
            'path' => 'attachments/logo.png',
            'cid' => 'logo@example.com',
            'is_inline' => true,
        ]);

        $html = '<p>Hello</p><img src="cid:logo@example.com" alt="Logo" />';

        $rewriter = new CidRewriter(new AttachmentStore);
        $rewritten = $rewriter->rewrite($html, $message->id);

        $expectedUrl = route('mailbox.attachments.inline', ['id' => 'att123']);
        expect($rewritten)->toContain($expectedUrl)
            ->and($rewritten)->not->toContain('cid:logo@example.com');
    });

    it('rewrites multiple CID references', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'logo.png',
            'mime_type' => 'image/png',
            'size' => 1000,
            'disk' => 'mailbox',
            'path' => 'attachments/logo.png',
            'cid' => 'logo@example.com',
            'is_inline' => true,
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att2',
            'message_id' => $message->id,
            'filename' => 'banner.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2000,
            'disk' => 'mailbox',
            'path' => 'attachments/banner.jpg',
            'cid' => 'banner@example.com',
            'is_inline' => true,
        ]);

        $html = '
            <p>Email content</p>
            <img src="cid:logo@example.com" alt="Logo" />
            <img src="cid:banner@example.com" alt="Banner" />
        ';

        $rewriter = new CidRewriter(new AttachmentStore);
        $rewritten = $rewriter->rewrite($html, $message->id);

        $logoUrl = route('mailbox.attachments.inline', ['id' => 'att1']);
        $bannerUrl = route('mailbox.attachments.inline', ['id' => 'att2']);

        expect($rewritten)->toContain($logoUrl)
            ->and($rewritten)->toContain($bannerUrl)
            ->and($rewritten)->not->toContain('cid:logo@example.com')
            ->and($rewritten)->not->toContain('cid:banner@example.com');
    });

    it('leaves HTML unchanged when no CID references exist', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $html = '<p>Hello world</p><img src="https://example.com/image.png" />';

        $rewriter = new CidRewriter(new AttachmentStore);
        $rewritten = $rewriter->rewrite($html, $message->id);

        expect($rewritten)->toBe($html);
    });

    it('leaves CID unchanged if attachment not found', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $html = '<img src="cid:nonexistent@example.com" />';

        $rewriter = new CidRewriter(new AttachmentStore);
        $rewritten = $rewriter->rewrite($html, $message->id);

        expect($rewritten)->toBe($html);
    });

    it('handles empty HTML', function () {
        $rewriter = new CidRewriter(new AttachmentStore);
        $rewritten = $rewriter->rewrite('', 1);

        expect($rewritten)->toBe('');
    });

    it('handles HTML with single quotes', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        $attachment = MailboxAttachment::query()->create([
            'id' => 'att456',
            'message_id' => $message->id,
            'filename' => 'image.png',
            'mime_type' => 'image/png',
            'size' => 1000,
            'disk' => 'mailbox',
            'path' => 'attachments/image.png',
            'cid' => 'image@example.com',
            'is_inline' => true,
        ]);

        $html = "<img src='cid:image@example.com' alt='Image' />";

        $rewriter = new CidRewriter(new AttachmentStore);
        $rewritten = $rewriter->rewrite($html, $message->id);

        $expectedUrl = route('mailbox.attachments.inline', ['id' => 'att456']);
        expect($rewritten)->toContain($expectedUrl);
    });

    it('gets inline attachments for a message', function () {
        $message = MailboxMessage::query()->create([
            'id' => 1,
            'timestamp' => time(),
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att1',
            'message_id' => $message->id,
            'filename' => 'inline.png',
            'mime_type' => 'image/png',
            'size' => 1000,
            'disk' => 'mailbox',
            'path' => 'attachments/inline.png',
            'cid' => 'inline@example.com',
            'is_inline' => true,
        ]);

        MailboxAttachment::query()->create([
            'id' => 'att2',
            'message_id' => $message->id,
            'filename' => 'regular.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2000,
            'disk' => 'mailbox',
            'path' => 'attachments/regular.pdf',
            'is_inline' => false,
        ]);

        $rewriter = new CidRewriter(new AttachmentStore);
        $inlineAttachments = $rewriter->getInlineAttachments($message->id);

        expect($inlineAttachments)->toHaveCount(1)
            ->and($inlineAttachments[0]->is_inline)->toBeTrue()
            ->and($inlineAttachments[0]->filename)->toBe('inline.png');
    });
});
