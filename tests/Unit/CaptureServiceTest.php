<?php

use Redberry\MailboxForLaravel\CaptureService;

describe(CaptureService::class, function () {
    it('stores raw message and returns key')->todo();
    it('normalizes message into structured array')->todo();
    it('extracts subject, from, to, cc, bcc, replyTo, date')->todo();
    it('extracts text and html bodies')->todo();
    it('extracts attachments with filename, mime, size, content_id, is_inline')->todo();
    it('rewrites cid: urls to asset route placeholders')->todo();
    it('persists normalized record via MessageStore')->todo();
    it('lists all messages ordered by timestamp desc')->todo();
    it('finds a message by id')->todo();
    it('deletes a message by id')->todo();
    it('purges messages older than configured ttl')->todo();
    it('guards against invalid email payloads')->todo();
});
