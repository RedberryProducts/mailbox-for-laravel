<?php

use Redberry\MailboxForLaravel\Facades\Inbox;

describe(Inbox::class, function () {
    it('proxies list/get/delete to CaptureService')->todo();
    it('returns paginated results when requested')->todo();
    it('guards against invalid ids')->todo();
});
