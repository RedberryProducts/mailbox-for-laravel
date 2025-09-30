<?php

namespace Redberry\MailboxForLaravel\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;

class AuthorizeInboxMiddleware
{
    public function handle($request, Closure $next)
    {
        $ability = config('inbox.gate', 'viewMailbox');

        if (!Gate::allows($ability)) {
            $redirect = config('inbox.unauthorized_redirect');

            if ($redirect) {
                return redirect($redirect);
            }
            abort(403);
        }

        return $next($request);
    }
}
