<?php

namespace Redberry\MailboxForLaravel\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;

class AuthorizeInboxMiddleware
{
    public function handle($request, Closure $next)
    {
        if (config('inbox.public', false)) {
            return $next($request);
        }

        $ability = config('inbox.gate', 'viewMailbox');
        // Let Gate decide (works with or without authenticated user; $user can be null)
        if (Gate::allows($ability)) {
            return $next($request);
        }

        abort(403);
    }
}
