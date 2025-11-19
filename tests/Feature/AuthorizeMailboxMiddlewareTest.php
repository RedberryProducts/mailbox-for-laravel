<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeMailboxMiddleware;

describe(AuthorizeMailboxMiddleware::class, function () {
    beforeEach(function () {
        Route::get('/mailbox-test', fn () => 'ok')->middleware(AuthorizeMailboxMiddleware::class);
    });

    it('allows access when Gate::allows(mailbox.view) returns true', function () {
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(true);

        $this->get('/mailbox-test')->assertOk();
    });

    it('denies access when Gate::denies(mailbox.view)', function () {
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertForbidden();
    });

    it('redirects to 403 page when no mailbox.unauthorized_redirect config is set', function () {
        config()->set('mailbox.unauthorized_redirect', null);
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertForbidden();
    });

    it('redirects to mailbox.unauthorized_redirect page when set in config', function () {
        config()->set('mailbox.unauthorized_redirect', '/custom-unauthorized');
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertRedirect('/custom-unauthorized');
    });

    it('denies access in production when config forbids public access', function () {
        config()->set('mailbox.enabled', false);
        config()->set('app.env', 'production');

        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertForbidden();
    });
});
