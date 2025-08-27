<?php

use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeInbox;

describe(AuthorizeInbox::class, function () {
    it('allows access when Gate::allows(inbox.view) returns true')->todo();
    it('denies access when Gate::denies(inbox.view)')->todo();
    it('allows access when config(inbox.public)=true')->todo();
    it('denies access in production when config forbids public access')->todo();
});
