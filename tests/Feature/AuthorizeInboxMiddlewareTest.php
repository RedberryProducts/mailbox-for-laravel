<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Middleware\AuthorizeInboxMiddleware;

describe(AuthorizeInboxMiddleware::class, function () {
    beforeEach(function () {
        Route::get('/mailbox-test', fn () => 'ok')->middleware(AuthorizeInboxMiddleware::class);
    });

    it('allows access when Gate::allows(inbox.view) returns true', function () {
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(true);

        $this->get('/mailbox-test')->assertOk();
    });

    it('denies access when Gate::denies(inbox.view)', function () {
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertForbidden();
    });

    it('redirects to 403 page when no inbox.unauthorized_redirect config is set', function () {
        config()->set('inbox.unauthorized_redirect', null);
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertForbidden();
    });

    it('redirects to inbox.unauthorized_redirect page when set in config', function () {
        config()->set('inbox.unauthorized_redirect', '/custom-unauthorized');
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertRedirect('/custom-unauthorized');
    });

    it('denies access in production when config forbids public access', function () {
        config()->set('inbox.public', false);
        $this->app->detectEnvironment(fn () => 'production');
        Gate::shouldReceive('allows')->with('viewMailbox')->andReturn(false);

        $this->get('/mailbox-test')->assertForbidden();
    });
});
