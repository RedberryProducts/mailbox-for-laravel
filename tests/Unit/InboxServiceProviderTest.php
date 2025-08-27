<?php

use Redberry\MailboxForLaravel\InboxServiceProvider;

describe(InboxServiceProvider::class, function () {
    it('registers config, routes, views, and install command')->todo();
    it('binds MessageStore contract to StoreManager->create() result')->todo();
    it('binds CaptureService as singleton with MessageStore dependency')->todo();
    it('registers inbox mail transport on boot')->todo();
    it('applies configured middleware to inbox routes')->todo();
    it('honors config(inbox.enabled)=false by not registering routes')->todo();
    it('defers transport registration to local/dev unless config enables in prod')->todo();
    it('merges default config values correctly')->todo();
});
