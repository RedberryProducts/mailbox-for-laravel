<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeMailboxMiddleware
{
    /**
     * Deny requests the gate rejects. Guests are sent to the configured
     * unauthorized_redirect (typically a login page); authenticated users
     * always get a 403, since logging in again would not help and a login
     * page that bounces authenticated users would loop.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ability = config('mailbox.gate', 'viewMailbox');

        if (! Gate::allows($ability)) {
            $redirect = config('mailbox.unauthorized_redirect');

            if ($redirect && $request->user() === null) {
                return redirect($redirect);
            }

            abort(403);
        }

        return $next($request);
    }
}
