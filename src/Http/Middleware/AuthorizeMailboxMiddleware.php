<?php

namespace Redberry\MailboxForLaravel\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;

class AuthorizeMailboxMiddleware
{
    public function handle($request, Closure $next)
    {
        $ability = config('mailbox.gate', 'viewMailbox');

        if (! Gate::allows($ability)) {
            $redirect = config('mailbox.unauthorized_redirect');

            if ($redirect) {
                return redirect($redirect);
            }
            abort(403);
        }

        return $next($request);
    }
}
