<?php

use Redberry\MailboxForLaravel\Transport\InboxTransport;

describe(InboxTransport::class, function () {
    it('sends messages through Symfony Transport while capturing raw')->todo();
    it('captures raw RFC822 content before delegating')->todo();
    it('uses CaptureService->storeRaw and returns storage key')->todo();
    it('does not call CaptureService when disabled via config')->todo();
    it('handles message with only text part')->todo();
    it('handles message with only html part')->todo();
    it('handles message with attachments')->todo();
    it('handles inline cid images and preserves references')->todo();
    it('throws a TransportException on underlying send failure and still does not corrupt store')->todo();
});
