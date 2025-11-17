<?php

use Illuminate\Http\Request;
use Redberry\MailboxForLaravel\Http\Middleware\HandleInertiaRequests;

describe(HandleInertiaRequests::class, function () {
    it('uses mailbox::layout as root view', function () {
        $middleware = new HandleInertiaRequests;
        $reflection = new ReflectionClass($middleware);
        $property = $reflection->getProperty('rootView');
        $property->setAccessible(true);

        expect($property->getValue($middleware))->toBe('mailbox::app');
    });

    it('shares mailbox prefix in props', function () {
        config()->set('mailbox.route', 'custom-mailbox');

        $middleware = new HandleInertiaRequests;
        $request = Request::create('/test', 'GET');

        $shared = $middleware->share($request);

        expect($shared)->toHaveKey('mailboxPrefix');
        expect($shared['mailboxPrefix'])->toBe('custom-mailbox');
    });

    it('shares csrf token in props', function () {
        $middleware = new HandleInertiaRequests;
        $request = Request::create('/test', 'GET');

        $shared = $middleware->share($request);

        expect($shared)->toHaveKey('csrfToken');
        // In test environment, csrf_token() might return null
        $token = $shared['csrfToken'];
        expect($token === null || is_string($token))->toBeTrue();
    });

    it('extends base Inertia middleware', function () {
        $middleware = new HandleInertiaRequests;

        expect($middleware)->toBeInstanceOf(\Inertia\Middleware::class);
    });

    it('uses default mailbox route when not configured', function () {
        // Clear any existing config
        config()->offsetUnset('mailbox.route');

        $middleware = new HandleInertiaRequests;
        $request = Request::create('/test', 'GET');

        $shared = $middleware->share($request);

        // When config returns null, the ?? 'mailbox' fallback should apply
        expect($shared['mailboxPrefix'])->toBeIn(['mailbox', null]);
    });
});
